<?php

use App\Enums\NewsDictionaryType;
use App\Models\NewsArticle;
use App\Models\NewsDictionaryEntry;
use App\Models\NewsSource;
use App\Models\User;

test('public feed is accessible without authentication', function () {
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->count(3)->create(['news_source_id' => $source->id]);

    $this->get(route('news.feed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news/Feed')
            ->has('articles.data', 3)
            ->where('currentCategory', null)
        );
});

test('public feed only shows articles from last 3 days', function () {
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subDay(),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subDays(5),
    ]);

    $this->get(route('news.feed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('articles.data', 1)
        );
});

test('authenticated feed loads with user context', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->count(2)->create(['news_source_id' => $source->id]);

    $this->actingAs($user)
        ->get(route('news.feed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news/Feed')
            ->has('articles.data')
            ->where('hasPreferences', false)
        );
});

test('feed can be filtered by category', function () {
    NewsDictionaryEntry::create([
        'type' => NewsDictionaryType::Competition,
        'key' => 'champions_league',
        'label' => 'Champions League',
        'aliases' => ['champions league'],
        'is_active' => true,
    ]);
    NewsDictionaryEntry::clearCache();

    $source = NewsSource::factory()->create();
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'competitions' => ['champions_league'],
        'published_at' => now(),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'competitions' => ['la_liga'],
        'published_at' => now(),
    ]);

    $this->get(route('news.feed', ['category' => 'champions_league']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentCategory', 'champions_league')
            ->has('articles.data', 1)
        );
});
