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
    public function getPublicFeed(?string $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $days = config('news.feed.public_feed_days', 3);

        $query = $this->baseQuery()
            ->where('news_articles.published_at', '>=', now()->subDays($days));

        if ($category !== null) {
            $this->applyCategoryFilter($query, $category);
        }

        return $this->paginateFeed($query, $perPage);
    }

    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        $search = '%'.mb_strtolower($query).'%';

        return $this->baseQuery()
            ->where(function (Builder $q) use ($search) {
                $q->whereRaw('LOWER(news_articles.title) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(news_articles.snippet) LIKE ?', [$search]);
            })
            ->orderByDesc('news_articles.published_at')
            ->paginate($perPage);
    }

    public function getPersonalizedFeed(User $user, ?string $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $preference = $user->newsPreference;

        if ($preference === null || ! $preference->hasPreferences()) {
            return $this->getPublicFeed($category, $perPage);
        }

        $query = $this->baseQuery();

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

    private function baseQuery(): Builder
    {
        return NewsArticle::query()
            ->with('source:id,name,slug,logo_url')
            ->select('news_articles.*');
    }

    private function applyCategoryFilter(Builder $query, string $category): void
    {
        $dictionary = NewsDictionaryEntry::getDictionary();

        if (isset($dictionary['competition'][$category])) {
            $query->whereJsonContains('news_articles.competitions', $category);
        } elseif (isset($dictionary['topic'][$category])) {
            $query->whereJsonContains('news_articles.topics', $category);
        }
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
            ->orderByDesc('is_breaking')
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    private function deduplicateByStoryGroup(Builder $query): Builder
    {
        $bestIds = DB::table(
            DB::raw('(
                SELECT na.id,
                       ROW_NUMBER() OVER (
                           PARTITION BY COALESCE(na.story_group_id, CAST(na.id AS TEXT))
                           ORDER BY ns.priority DESC, na.published_at DESC
                       ) AS rn
                FROM news_articles na
                JOIN news_sources ns ON ns.id = na.news_source_id
            ) AS ranked')
        )->where('rn', 1)->pluck('id');

        return $query->whereIn('news_articles.id', $bestIds);
    }
}
