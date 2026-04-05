<?php

namespace Database\Factories;

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Models\NewsArticleInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticleInteraction>
 */
class NewsArticleInteractionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'news_article_id' => NewsArticle::factory(),
            'type' => NewsInteractionType::View,
        ];
    }

    public function bookmark(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NewsInteractionType::Bookmark,
        ]);
    }
}
