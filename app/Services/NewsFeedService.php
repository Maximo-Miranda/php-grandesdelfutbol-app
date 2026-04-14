<?php

namespace App\Services;

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Models\NewsArticleInteraction;
use App\Models\NewsDictionaryEntry;
use App\Models\User;
use App\Models\UserNewsPreference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NewsFeedService
{
    /**
     * Mapping from dictionary type to the JSON column storing that entity
     * on news_articles. Used by both the "exists?" check and the query
     * filter, so adding a new type is a one-line change.
     *
     * @var array<string, string>
     */
    private const CATEGORY_COLUMN_MAP = [
        'competition' => 'news_articles.competitions',
        'topic' => 'news_articles.topics',
        'team' => 'news_articles.teams',
    ];

    public function getPublicFeed(?string $category = null, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $days = config('news.feed.public_feed_days', 3);

        $query = $this->baseQuery($user)
            ->where('news_articles.published_at', '>=', now()->subDays($days));

        if ($category !== null) {
            $this->applyCategoryFilter($query, $category);
        }

        return $this->paginateFeed($query, $perPage);
    }

    public function search(string $query, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        // Scout's database engine applies `to_tsvector('spanish', ...) @@
        // plainto_tsquery('spanish', ?)` across title/snippet/full_content
        // (see NewsArticle::toSearchableArray) and orders by ts_rank
        // automatically when running on Postgres. The `query` callback
        // hydrates the same relations/counts/bookmarks the feed uses so
        // article cards render identically to the regular feed.
        return NewsArticle::search($query)
            ->query(fn (Builder $q) => $this->applyCardSelections($q, $user))
            ->paginate($perPage);
    }

    /**
     * Eager-load the relations and aggregate columns a NewsArticleCard needs.
     * Matches `baseQuery()` but takes an existing builder so Scout's
     * `query()` callback can layer it onto the search query.
     */
    private function applyCardSelections(Builder $query, ?User $user = null): Builder
    {
        $query->with('source:id,name,slug,logo_url')
            ->withCount([
                'comments as comments_count',
                'interactions as likes_count' => fn (Builder $q) => $q
                    ->where('type', NewsInteractionType::Like),
            ]);

        if ($user !== null) {
            $query->withExists([
                'interactions as is_bookmarked' => fn (Builder $q) => $q
                    ->where('user_id', $user->id)
                    ->where('type', NewsInteractionType::Bookmark),
                'interactions as is_liked' => fn (Builder $q) => $q
                    ->where('user_id', $user->id)
                    ->where('type', NewsInteractionType::Like),
            ]);
        }

        return $query;
    }

    public function getPersonalizedFeed(User $user, ?string $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $preference = $user->newsPreference;

        if ($preference === null || ! $preference->hasPreferences()) {
            return $this->getPublicFeed($category, $perPage, $user);
        }

        $query = $this->baseQuery($user);

        if ($category !== null) {
            $this->applyCategoryFilter($query, $category);
        } else {
            $this->applyPreferenceFilter($query, $preference);
        }

        return $this->paginateFeed($query, $perPage);
    }

    public function recordInteraction(User $user, NewsArticle $article, NewsInteractionType $type, ?int $timeSpent = null): void
    {
        NewsArticleInteraction::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'news_article_id' => $article->id,
                'type' => $type,
            ],
            [
                'time_spent_seconds' => $timeSpent,
            ],
        );
    }

    public function toggleBookmark(User $user, NewsArticle $article): bool
    {
        return $this->toggleInteraction($user, $article, NewsInteractionType::Bookmark);
    }

    public function toggleLike(User $user, NewsArticle $article): bool
    {
        return $this->toggleInteraction($user, $article, NewsInteractionType::Like);
    }

    /**
     * Shared toggle for interactions that behave as on/off switches
     * (bookmark, like). Returns true when the interaction is now active.
     */
    private function toggleInteraction(User $user, NewsArticle $article, NewsInteractionType $type): bool
    {
        $deleted = NewsArticleInteraction::query()
            ->where('user_id', $user->id)
            ->where('news_article_id', $article->id)
            ->where('type', $type)
            ->delete();

        if ($deleted > 0) {
            return false;
        }

        NewsArticleInteraction::query()->create([
            'user_id' => $user->id,
            'news_article_id' => $article->id,
            'type' => $type,
        ]);

        return true;
    }

    /**
     * Record a share interaction. Idempotent per user/article.
     */
    public function recordShare(User $user, NewsArticle $article): void
    {
        $this->recordInteraction($user, $article, NewsInteractionType::Share);
    }

    public function getBookmarkedFeed(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($user)
            ->whereHas('interactions', function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('type', NewsInteractionType::Bookmark);
            })
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    private function baseQuery(?User $user = null): Builder
    {
        $query = NewsArticle::query()->select('news_articles.*');

        return $this->applyCardSelections($query, $user);
    }

    public static function categoryExists(string $category): bool
    {
        $dictionary = NewsDictionaryEntry::getDictionary();

        foreach (array_keys(self::CATEGORY_COLUMN_MAP) as $type) {
            if (isset($dictionary[$type][$category])) {
                return true;
            }
        }

        return false;
    }

    private function applyCategoryFilter(Builder $query, string $category): void
    {
        $dictionary = NewsDictionaryEntry::getDictionary();

        foreach (self::CATEGORY_COLUMN_MAP as $type => $column) {
            if (isset($dictionary[$type][$category])) {
                $query->whereJsonContains($column, $category);

                return;
            }
        }

        // Unknown category — force empty result set so the controller can
        // return a clear "no results" state instead of silently showing all.
        $query->whereRaw('1 = 0');
    }

    private function applyPreferenceFilter(Builder $query, UserNewsPreference $preference): void
    {
        $query->where(function (Builder $q) use ($preference) {
            foreach ($preference->teams ?? [] as $team) {
                $q->orWhereJsonContains('news_articles.teams', $team);
            }

            foreach ($preference->competitions ?? [] as $comp) {
                $q->orWhereJsonContains('news_articles.competitions', $comp);
            }

            foreach ($preference->topics ?? [] as $topic) {
                $q->orWhereJsonContains('news_articles.topics', $topic);
            }
        });
    }

    private function paginateFeed(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $this->deduplicateByStoryGroup($query)
            ->orderByDesc('news_articles.published_at')
            ->paginate($perPage);
    }

    /**
     * Keep a single article per story_group_id (the one from the highest-
     * priority source, breaking ties by published_at DESC). Implemented as a
     * pure SQL subquery so it stays in the database without pulling IDs into
     * PHP memory, scaling past hundreds of thousands of articles.
     */
    private function deduplicateByStoryGroup(Builder $query): Builder
    {
        // Bound the window function to the cleanup retention window so it
        // never scans the entire table as article volume grows.
        $maxAgeDays = (int) config('news.feed.max_article_age_days', 30);

        $ranked = NewsArticle::query()
            ->select('news_articles.id')
            ->join('news_sources', 'news_sources.id', '=', 'news_articles.news_source_id')
            ->where('news_articles.published_at', '>=', now()->subDays($maxAgeDays))
            ->selectRaw(
                'ROW_NUMBER() OVER (
                    PARTITION BY COALESCE(news_articles.story_group_id, CAST(news_articles.id AS TEXT))
                    ORDER BY news_sources.priority DESC, news_articles.published_at DESC
                ) AS rn'
            );

        return $query->joinSub(
            DB::query()->fromSub($ranked, 'ranked')->where('rn', 1),
            'deduped',
            'deduped.id',
            '=',
            'news_articles.id',
        );
    }
}
