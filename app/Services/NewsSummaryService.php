<?php

namespace App\Services;

use App\Ai\Agents\SummarizeNewsArticle;
use App\Models\NewsArticle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Exceptions\RateLimitedException;

class NewsSummaryService
{
    private const CACHE_KEY = 'news:ai_summary_count';

    private const RATE_LIMITER = 'gemini-api';

    public function dailyLimit(): int
    {
        return (int) config('news.ai.daily_limit', 1200);
    }

    public function perMinuteLimit(): int
    {
        return (int) config('news.ai.per_minute_limit', 200);
    }

    /**
     * Minimum content length (in characters) before a summary is worth generating.
     * Below ~1500 chars (≈1 minute of reading) a summary barely saves the user any time.
     */
    public function minContentLength(): int
    {
        return (int) config('news.ai.min_content_length', 1500);
    }

    public function hasEnoughContent(NewsArticle $article): bool
    {
        return mb_strlen($this->articleContent($article)) >= $this->minContentLength();
    }

    /**
     * Estimate reading time in minutes using ~250 wpm and a ~5 character-per-word
     * heuristic, to avoid tokenizing every article. Always returns at least 1.
     */
    public function estimateReadingMinutes(NewsArticle $article): int
    {
        $chars = mb_strlen($this->articleContent($article));

        if ($chars === 0) {
            return 1;
        }

        return max(1, (int) ceil(($chars / 5) / 250));
    }

    private function articleContent(NewsArticle $article): string
    {
        return $article->full_content ?? $article->snippet ?? '';
    }

    /**
     * Generate and persist a summary for the given article.
     * Reuses an existing summary from the same story group when available.
     * Returns true when a summary was persisted, false otherwise.
     */
    public function summarize(NewsArticle $article): bool
    {
        if ($article->ai_summary !== null) {
            return true;
        }

        if ($existing = $this->findExistingSummaryInGroup($article)) {
            $this->propagateSummaryToGroup($article, $existing);

            return true;
        }

        if (! $this->hasEnoughContent($article)) {
            return false;
        }

        if (! $this->canGenerate()) {
            return false;
        }

        $result = RateLimiter::attempt(
            self::RATE_LIMITER,
            $this->perMinuteLimit(),
            fn () => $this->generateAndPropagate($article),
        );

        if ($result === false) {
            Log::info("Skipping summary for article {$article->id}: per-minute rate limit reached.");

            return false;
        }

        return $result;
    }

    private function generateAndPropagate(NewsArticle $article): bool
    {
        $text = collect([$article->title, $this->articleContent($article)])->filter()->implode("\n\n");

        try {
            $response = SummarizeNewsArticle::make()
                ->prompt("Resume esta noticia de fútbol:\n\n{$text}");
        } catch (RateLimitedException) {
            $this->backoffLocalLimiter();
            Log::warning("Gemini rate limited on article {$article->id}, backing off.");

            return false;
        } catch (\Throwable $e) {
            Log::warning("Failed to summarize article {$article->id}: {$e->getMessage()}");

            return false;
        }

        $this->incrementUsage();
        $this->propagateSummaryToGroup($article, $response->text);

        return true;
    }

    private function backoffLocalLimiter(): void
    {
        while (RateLimiter::remaining(self::RATE_LIMITER, $this->perMinuteLimit()) > 0) {
            RateLimiter::hit(self::RATE_LIMITER);
        }
    }

    public function canGenerate(): bool
    {
        return $this->usedToday() < $this->dailyLimit();
    }

    public function usedToday(): int
    {
        return (int) Cache::get(self::CACHE_KEY, 0);
    }

    public function remainingToday(): int
    {
        return max(0, $this->dailyLimit() - $this->usedToday());
    }

    private function findExistingSummaryInGroup(NewsArticle $article): ?string
    {
        if (! $article->story_group_id) {
            return null;
        }

        return NewsArticle::query()
            ->where('story_group_id', $article->story_group_id)
            ->whereKeyNot($article->id)
            ->whereNotNull('ai_summary')
            ->value('ai_summary');
    }

    private function propagateSummaryToGroup(NewsArticle $article, string $summary): void
    {
        if (! $article->story_group_id) {
            $article->update(['ai_summary' => $summary]);

            return;
        }

        NewsArticle::query()
            ->where('story_group_id', $article->story_group_id)
            ->whereNull('ai_summary')
            ->update(['ai_summary' => $summary]);
    }

    private function incrementUsage(): void
    {
        Cache::add(self::CACHE_KEY, 0, now()->endOfDay());
        Cache::increment(self::CACHE_KEY);
    }
}
