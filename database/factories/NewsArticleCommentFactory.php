<?php

namespace Database\Factories;

use App\Models\NewsArticle;
use App\Models\NewsArticleComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticleComment>
 */
class NewsArticleCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_article_id' => NewsArticle::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(10),
        ];
    }
}
