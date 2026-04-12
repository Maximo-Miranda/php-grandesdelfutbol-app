<?php

namespace App\Jobs;

use App\Enums\NewsContentType;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Services\ArticleCategorizationService;
use App\Services\RssFetcherService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchNewsFromSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [30, 60];

    public function __construct(public NewsSource $source) {}

    public function uniqueId(): string
    {
        return (string) $this->source->id;
    }

    public function handle(
        RssFetcherService $rssFetcher,
        ArticleCategorizationService $categorizer,
    ): void {
        $entries = $rssFetcher->fetch($this->source);

        $existingIds = NewsArticle::query()
            ->where('news_source_id', $this->source->id)
            ->whereIn('external_id', $entries->pluck('external_id')->filter())
            ->pluck('external_id')
            ->flip();

        $created = 0;

        foreach ($entries as $entry) {
            try {
                if ($existingIds->has($entry['external_id'])) {
                    continue;
                }

                $categories = $categorizer->categorize([
                    'title' => $entry['title'],
                    'snippet' => $entry['snippet'],
                ]);

                $publishedAt = $this->parseDate($entry['published_at']) ?? now();

                $article = NewsArticle::query()->create([
                    'news_source_id' => $this->source->id,
                    'external_id' => $entry['external_id'],
                    'title' => $entry['title'],
                    'snippet' => $entry['snippet'],
                    'full_content' => $entry['full_content'] ?? null,
                    'image_url' => $entry['image_url'],
                    'image_urls' => $entry['image_urls'] ?: null,
                    'original_url' => $entry['original_url'],
                    'author' => $entry['author'],
                    'content_type' => NewsContentType::Article,
                    'teams' => $categories['teams'] ?: null,
                    'competitions' => $categories['competitions'] ?: null,
                    'topics' => $categories['topics'] ?: null,
                    'is_breaking' => $categories['is_breaking'],
                    'published_at' => $publishedAt,
                ]);

                $categorizer->assignStoryGroup($article);

                $created++;
            } catch (\Throwable $e) {
                Log::warning("Failed to process article '{$entry['title']}' from {$this->source->name}: {$e->getMessage()}");
            }
        }

        $this->source->update(['last_fetched_at' => now()]);

        if ($created > 0) {
            Log::info("Fetched {$created} new articles from {$this->source->name}");
        }
    }

    private function parseDate(?string $date): ?Carbon
    {
        if (blank($date)) {
            return null;
        }

        return rescue(fn () => Carbon::parse($date));
    }
}
