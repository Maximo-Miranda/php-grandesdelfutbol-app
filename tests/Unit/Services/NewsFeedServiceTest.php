<?php

use App\Enums\NewsDictionaryType;
use App\Models\NewsArticle;
use App\Models\NewsDictionaryEntry;
use App\Models\NewsSource;
use App\Models\User;
use App\Services\NewsFeedService;

beforeEach(function () {
    $this->service = new NewsFeedService;

    NewsDictionaryEntry::create([
        'type' => NewsDictionaryType::Competition,
        'key' => 'la_liga',
        'label' => 'La Liga',
        'aliases' => ['la liga'],
        'is_active' => true,
    ]);
    NewsDictionaryEntry::clearCache();
});

test('search finds articles by title substring', function () {
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'title' => 'Real Madrid vence al Barcelona en el clásico',
        'published_at' => now(),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'title' => 'Liverpool ficha a un nuevo delantero',
        'published_at' => now(),
    ]);

    $results = $this->service->search('clásico');

    expect($results->items())->toHaveCount(1);
    expect($results->first()->title)->toContain('clásico');
});

test('search is case-insensitive and matches snippet', function () {
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'title' => 'Noticia deportiva',
        'snippet' => 'El jugador marcó un GOLAZO de media cancha',
        'published_at' => now(),
    ]);

    $results = $this->service->search('golazo');

    expect($results->items())->toHaveCount(1);
});

test('search matches words that only appear in full_content', function () {
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'title' => 'Crónica del partido',
        'snippet' => 'Resumen breve del encuentro.',
        'full_content' => 'El delantero colombiano Juan Fernando Quintero marcó el gol del triunfo.',
        'published_at' => now(),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'title' => 'Otra noticia',
        'snippet' => 'Contenido sin relación',
        'full_content' => 'Texto libre sin menciones relevantes.',
        'published_at' => now(),
    ]);

    $results = $this->service->search('Quintero');

    expect($results->items())->toHaveCount(1);
    expect($results->first()->full_content)->toContain('Quintero');
});

test('deduplication keeps highest-priority source per story group', function () {
    $highPriority = NewsSource::factory()->create(['priority' => 10]);
    $lowPriority = NewsSource::factory()->create(['priority' => 1]);
    $storyGroupId = 'story-group-test';

    $winner = NewsArticle::factory()->create([
        'news_source_id' => $highPriority->id,
        'story_group_id' => $storyGroupId,
        'published_at' => now(),
    ]);
    $loser = NewsArticle::factory()->create([
        'news_source_id' => $lowPriority->id,
        'story_group_id' => $storyGroupId,
        'published_at' => now(),
    ]);

    $feed = $this->service->getPublicFeed();

    $ids = collect($feed->items())->pluck('id')->all();

    expect($ids)->toContain($winner->id);
    expect($ids)->not->toContain($loser->id);
});

test('deduplication keeps articles without a story group', function () {
    $source = NewsSource::factory()->create();

    NewsArticle::factory()->count(3)->create([
        'news_source_id' => $source->id,
        'story_group_id' => null,
        'published_at' => now(),
    ]);

    $feed = $this->service->getPublicFeed();

    expect($feed->total())->toBe(3);
});

test('category filter matches articles by competition', function () {
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'competitions' => ['la_liga'],
        'published_at' => now(),
    ]);
    NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'competitions' => ['champions_league'],
        'published_at' => now(),
    ]);

    $feed = $this->service->getPublicFeed('la_liga');

    expect($feed->total())->toBe(1);
});

test('unknown category returns empty feed', function () {
    $source = NewsSource::factory()->create();
    NewsArticle::factory()->count(3)->create([
        'news_source_id' => $source->id,
        'published_at' => now(),
    ]);

    $feed = $this->service->getPublicFeed('totally_unknown_category');

    expect($feed->total())->toBe(0);
});

test('categoryExists returns true for dictionary entries only', function () {
    expect(NewsFeedService::categoryExists('la_liga'))->toBeTrue();
    expect(NewsFeedService::categoryExists('ghost_category'))->toBeFalse();
});

test('feed orders articles by published_at regardless of is_breaking flag', function () {
    $source = NewsSource::factory()->create();

    $oldBreaking = NewsArticle::factory()->breaking()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subDays(2),
    ]);
    $recentNormal = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subHour(),
    ]);
    $newestBreaking = NewsArticle::factory()->breaking()->create([
        'news_source_id' => $source->id,
        'published_at' => now()->subMinutes(30),
    ]);

    $feed = $this->service->getPublicFeed();

    $ids = collect($feed->items())->pluck('id')->all();

    expect($ids)->toBe([$newestBreaking->id, $recentNormal->id, $oldBreaking->id]);
});

test('toggleBookmark creates and removes interaction', function () {
    $user = User::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => NewsSource::factory()->create()->id,
    ]);

    expect($this->service->toggleBookmark($user, $article))->toBeTrue();
    expect($this->service->toggleBookmark($user, $article))->toBeFalse();
});

test('toggleLike creates and removes interaction', function () {
    $user = User::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => NewsSource::factory()->create()->id,
    ]);

    expect($this->service->toggleLike($user, $article))->toBeTrue();
    expect($this->service->toggleLike($user, $article))->toBeFalse();
});
