<?php

namespace Database\Factories;

use App\Models\NewsArticle;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    public function definition(): array
    {
        $fullContent = fake()->paragraphs(15, true);

        return [
            'ulid' => (string) Str::ulid(),
            'news_source_id' => NewsSource::factory(),
            'external_id' => fake()->uuid(),
            'title' => fake()->sentence(8),
            'snippet' => mb_substr($fullContent, 0, 500),
            'full_content' => $fullContent,
            'image_url' => fake()->imageUrl(800, 400),
            'original_url' => fake()->url(),
            'author' => fake()->name(),
            'is_breaking' => false,
            'story_group_id' => (string) Str::uuid(),
            'published_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ];
    }

    public function withoutContent(): static
    {
        return $this->state(fn () => [
            'snippet' => null,
            'full_content' => null,
        ]);
    }

    public function breaking(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_breaking' => true,
        ]);
    }

    public function withCompetitions(array $competitions): static
    {
        return $this->state(fn (array $attributes) => [
            'competitions' => $competitions,
        ]);
    }

    public function withTeams(array $teams): static
    {
        return $this->state(fn (array $attributes) => [
            'teams' => $teams,
        ]);
    }

    public function withTopics(array $topics): static
    {
        return $this->state(fn (array $attributes) => [
            'topics' => $topics,
        ]);
    }

    public function old(int $days = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->subDays($days),
        ]);
    }
}
