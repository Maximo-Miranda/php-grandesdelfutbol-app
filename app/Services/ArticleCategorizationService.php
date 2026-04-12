<?php

namespace App\Services;

use App\Models\NewsArticle;
use App\Models\NewsDictionaryEntry;
use Illuminate\Support\Str;

class ArticleCategorizationService
{
    /**
     * @param  array{title: string, snippet: string|null}  $articleData
     * @return array{teams: list<string>, competitions: list<string>, topics: list<string>, is_breaking: bool}
     */
    public function categorize(array $articleData): array
    {
        $text = mb_strtolower($articleData['title'].' '.($articleData['snippet'] ?? ''));
        $dictionary = NewsDictionaryEntry::getDictionary();

        $teams = $this->matchEntities($text, $dictionary['team'] ?? []);
        $competitions = $this->matchEntities($text, $dictionary['competition'] ?? []);
        $topics = $this->matchEntities($text, $dictionary['topic'] ?? []);

        $matchedKeys = [...$teams, ...$competitions, ...$topics];

        if ($matchedKeys !== []) {
            NewsDictionaryEntry::whereIn('key', $matchedKeys)->increment('matches_count');
        }

        return [
            'teams' => $teams,
            'competitions' => $competitions,
            'topics' => $topics,
            'is_breaking' => $this->matchesAny(mb_strtolower($articleData['title']), $dictionary['breaking_keyword'] ?? []),
        ];
    }

    public function assignStoryGroup(NewsArticle $article): void
    {
        $timeWindow = config('news.clustering.time_window_hours', 6);
        $minTitleSimilarity = config('news.clustering.min_title_similarity', 0.7);

        $candidates = NewsArticle::query()
            ->whereKeyNot($article->id)
            ->where('news_source_id', '!=', $article->news_source_id)
            ->whereBetween('published_at', [
                $article->published_at->subHours($timeWindow),
                $article->published_at->addHours($timeWindow),
            ])
            ->whereNotNull('story_group_id')
            ->get(['id', 'title', 'story_group_id']);

        foreach ($candidates as $candidate) {
            $similarity = $this->calculateTitleSimilarity($article->title, $candidate->title);

            if ($similarity >= $minTitleSimilarity) {
                $article->update(['story_group_id' => $candidate->story_group_id]);

                return;
            }
        }

        $article->update(['story_group_id' => (string) Str::uuid()]);
    }

    public function calculateTitleSimilarity(string $titleA, string $titleB): float
    {
        $stopWords = config('news-dictionary.stop_words', []);

        $tokensA = $this->tokenize($titleA, $stopWords);
        $tokensB = $this->tokenize($titleB, $stopWords);

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        return count($intersection) / count($union);
    }

    /**
     * @param  array<string, list<string>>  $dictionary
     * @return list<string>
     */
    private function matchEntities(string $text, array $dictionary): array
    {
        $matched = [];

        foreach ($dictionary as $key => $aliases) {
            if ($this->containsAlias($text, $aliases)) {
                $matched[] = $key;
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, list<string>>  $dictionary
     */
    private function matchesAny(string $text, array $dictionary): bool
    {
        foreach ($dictionary as $aliases) {
            if ($this->containsAlias($text, $aliases)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function containsAlias(string $text, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            $needle = mb_strtolower(trim($alias));

            if ($needle === '') {
                continue;
            }

            $pattern = '/(?<!\pL)'.preg_quote($needle, '/').'(?!\pL)/u';

            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $stopWords
     * @return list<string>
     */
    private function tokenize(string $text, array $stopWords): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_diff($words, $stopWords));
    }
}
