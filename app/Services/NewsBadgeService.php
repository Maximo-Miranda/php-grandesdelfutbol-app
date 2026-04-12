<?php

namespace App\Services;

use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class NewsBadgeService
{
    private const CACHE_TTL_SECONDS = 60;

    private const CACHE_TAG = 'news-unread';

    private ?bool $hasTags = null;

    /**
     * Compute the unread-news badge payload for a user. Results are cached for
     * a short window so navigating between tabs doesn't hit the database on
     * every request. The cache is invalidated on two events:
     *   1. The user opens the news feed (their `news_last_seen_at` changes)
     *   2. New articles are ingested via FetchNewsFromSource (global flush)
     *
     * @return array{count: int, hasBreaking: bool}
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return ['count' => 0, 'hasBreaking' => false];
        }

        return $this->tagged()->remember(
            $this->cacheKey($user),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeForUser($user),
        );
    }

    public function forget(User $user): void
    {
        $this->tagged()->forget($this->cacheKey($user));
    }

    /**
     * Flush every user's badge cache. Call this after new articles land so
     * the UI can reflect the new volume within one polling cycle. Without
     * tag support we skip and rely on the short TTL — flushing the untagged
     * store would wipe unrelated app cache entries.
     */
    public function flushAll(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags([self::CACHE_TAG])->flush();
    }

    /** @return array{count: int, hasBreaking: bool} */
    private function computeForUser(User $user): array
    {
        $since = $user->news_last_seen_at ?? now()->subDay();
        $articlesSince = NewsArticle::query()->where('published_at', '>', $since);

        $count = $articlesSince->count();

        if ($count === 0) {
            return ['count' => 0, 'hasBreaking' => false];
        }

        return [
            'count' => $count,
            'hasBreaking' => (clone $articlesSince)->where('is_breaking', true)->exists(),
        ];
    }

    private function cacheKey(User $user): string
    {
        return "news:unread:{$user->id}";
    }

    private function tagged(): Repository
    {
        return $this->supportsTags()
            ? Cache::tags([self::CACHE_TAG])
            : Cache::store();
    }

    private function supportsTags(): bool
    {
        return $this->hasTags ??= method_exists(Cache::getStore(), 'tags');
    }
}
