<?php

use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Models\User;

test('shared newsUnreadCount is zero for guests', function () {
    NewsArticle::factory()->count(3)->create([
        'news_source_id' => NewsSource::factory()->create()->id,
        'published_at' => now(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('newsUnreadCount.count', 0)
            ->where('newsUnreadCount.hasBreaking', false)
        );
});

test('first-time authenticated user sees last 24h as unread count', function () {
    $user = User::factory()->create(['news_last_seen_at' => null]);
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->count(2)->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subHours(2),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subDays(3),
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('newsUnreadCount.count', 2));
});

test('count reflects articles published after news_last_seen_at', function () {
    $user = User::factory()->create(['news_last_seen_at' => now()->subHour()]);
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->count(3)->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subMinutes(30),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('newsUnreadCount.count', 3));
});

test('hasBreaking flips when a new article is breaking', function () {
    $user = User::factory()->create(['news_last_seen_at' => now()->subHour()]);
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subMinutes(5),
        'is_breaking' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('newsUnreadCount.count', 1)
            ->where('newsUnreadCount.hasBreaking', true)
        );
});

test('visiting the news feed resets news_last_seen_at and clears the badge', function () {
    $user = User::factory()->create(['news_last_seen_at' => now()->subDay()]);
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->count(5)->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('news.feed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('newsUnreadCount.count', 0));

    expect($user->fresh()->news_last_seen_at)->not->toBeNull();
    expect($user->fresh()->news_last_seen_at->isAfter(now()->subMinute()))->toBeTrue();
});

test('badge stays at zero after visiting news and navigating to another page', function () {
    $user = User::factory()->create(['news_last_seen_at' => now()->subDay()]);
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->count(15)->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subHours(2),
    ]);

    // Enter feed — should mark as seen and return count 0.
    $this->actingAs($user)
        ->get(route('news.feed'))
        ->assertInertia(fn ($page) => $page->where('newsUnreadCount.count', 0));

    // Leave feed — should still see count 0 (no new articles published in between).
    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('newsUnreadCount.count', 0));
});
