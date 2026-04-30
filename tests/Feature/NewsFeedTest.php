<?php

use App\Enums\NewsDictionaryType;
use App\Models\NewsArticle;
use App\Models\NewsDictionaryEntry;
use App\Models\User;

test('guests cannot access the news feed', function () {
    $this->get(route('news.feed'))
        ->assertRedirect(route('login'));
});

test('feed only shows articles from last 3 days when user has no preferences', function () {
    $user = User::factory()->create();
    NewsArticle::factory()->create([
        'published_at' => now()->subDay(),
    ]);
    NewsArticle::factory()->create([
        'published_at' => now()->subDays(5),
    ]);

    $this->actingAs($user)
        ->get(route('news.feed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('articles.data', 1)
        );
});

test('authenticated feed loads with user context', function () {
    $user = User::factory()->create();
    NewsArticle::factory()->count(2)->create();

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
    $user = User::factory()->create();

    NewsDictionaryEntry::create([
        'type' => NewsDictionaryType::Competition,
        'key' => 'champions_league',
        'label' => 'Champions League',
        'aliases' => ['champions league'],
        'is_active' => true,
    ]);
    NewsDictionaryEntry::clearCache();

    NewsArticle::factory()->create([
        'competitions' => ['champions_league'],
        'published_at' => now(),
    ]);
    NewsArticle::factory()->create([
        'competitions' => ['la_liga'],
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('news.feed', ['category' => 'champions_league']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentCategory', 'champions_league')
            ->has('articles.data', 1)
        );
});
