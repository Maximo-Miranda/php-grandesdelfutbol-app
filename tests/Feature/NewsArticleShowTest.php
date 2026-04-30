<?php

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Models\User;

test('guests cannot access article detail', function () {
    $article = NewsArticle::factory()->create();

    $this->get(route('news.show', $article))
        ->assertRedirect(route('login'));
});

test('article detail is accessible for authenticated users', function () {
    $user = User::factory()->create();
    $article = NewsArticle::factory()->create();

    $this->actingAs($user)
        ->get(route('news.show', $article))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news/Show')
            ->where('article.ulid', $article->ulid)
            ->where('isBookmarked', false)
        );
});

test('viewing article as authenticated user records view interaction', function () {
    $user = User::factory()->create();
    $article = NewsArticle::factory()->create();

    $this->actingAs($user)
        ->get(route('news.show', $article))
        ->assertOk();

    $this->assertDatabaseHas('news_article_interactions', [
        'user_id' => $user->id,
        'news_article_id' => $article->id,
        'type' => NewsInteractionType::View->value,
    ]);
});

test('viewing article as guest does not record interaction', function () {
    $article = NewsArticle::factory()->create();

    $this->get(route('news.show', $article))
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('news_article_interactions', 0);
});

test('article shows related articles from same story group', function () {
    $user = User::factory()->create();
    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();
    $storyGroupId = fake()->uuid();

    $article = NewsArticle::factory()->create([
        'news_source_id' => $source1->id,
        'story_group_id' => $storyGroupId,
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source2->id,
        'story_group_id' => $storyGroupId,
    ]);

    $this->actingAs($user)
        ->get(route('news.show', $article))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('relatedArticles', 1)
            ->where('storySourceCount', 2)
        );
});
