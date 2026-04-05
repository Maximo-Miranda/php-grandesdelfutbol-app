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

        return [
            'teams' => $this->matchEntities($text, $dictionary['team'] ?? []),
            'competitions' => $this->matchEntities($text, $dictionary['competition'] ?? []),
            'topics' => $this->matchEntities($text, $dictionary['topic'] ?? []),
            'is_breaking' => $this->isBreaking($articleData['title'], $dictionary['breaking_keyword'] ?? []),
        ];
    }

    public function assignStoryGroup(NewsArticle $article): void
    {
        $timeWindow = config('news.clustering.time_window_hours', 6);
        $minEntityOverlap = config('news.clustering.min_entity_overlap', 2);
        $minTitleSimilarity = config('news.clustering.min_title_similarity', 0.4);

        $candidates = NewsArticle::query()
            ->where('id', '!=', $article->id)
            ->where('news_source_id', '!=', $article->news_source_id)
            ->where('published_at', '>=', $article->published_at->subHours($timeWindow))
            ->where('published_at', '<=', $article->published_at->addHours($timeWindow))
            ->whereNotNull('story_group_id')
            ->get(['id', 'title', 'teams', 'competitions', 'story_group_id']);

        foreach ($candidates as $candidate) {
            $titleSimilarity = $this->calculateTitleSimilarity($article->title, $candidate->title);

            if ($titleSimilarity >= 0.8) {
                $article->update(['story_group_id' => $candidate->story_group_id]);

                return;
            }

            if ($titleSimilarity >= $minTitleSimilarity) {
                $entityOverlap = $this->countEntityOverlap(
                    $article->teams ?? [],
                    $article->competitions ?? [],
                    $candidate->teams ?? [],
                    $candidate->competitions ?? [],
                );

                if ($entityOverlap >= $minEntityOverlap) {
                    $article->update(['story_group_id' => $candidate->story_group_id]);

                    return;
                }
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
     * @return list<string>
     */
    private function matchEntities(string $text, array $dictionary): array
    {
        $matched = [];

        foreach ($dictionary as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($text, mb_strtolower($alias))) {
                    $matched[] = $key;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, list<string>>  $breakingKeywords
     */
    private function isBreaking(string $title, array $breakingKeywords): bool
    {
        $lower = mb_strtolower($title);

        foreach ($breakingKeywords as $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($lower, mb_strtolower($alias))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function countEntityOverlap(array $teamsA, array $compsA, array $teamsB, array $compsB): int
    {
        return count(array_intersect($teamsA, $teamsB)) + count(array_intersect($compsA, $compsB));
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
