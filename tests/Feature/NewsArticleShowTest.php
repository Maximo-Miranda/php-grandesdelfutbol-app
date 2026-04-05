<?php

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Models\User;

test('article detail is accessible without authentication', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

    $this->get(route('news.show', $article))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news/Show')
            ->where('article.ulid', $article->ulid)
            ->where('isBookmarked', false)
        );
});

test('viewing article as authenticated user records view interaction', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

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
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

    $this->get(route('news.show', $article))
        ->assertOk();

    $this->assertDatabaseCount('news_article_interactions', 0);
});

test('article shows related articles from same story group', function () {
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

    $this->get(route('news.show', $article))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('relatedArticles', 1)
            ->where('storySourceCount', 2)
        );
});
