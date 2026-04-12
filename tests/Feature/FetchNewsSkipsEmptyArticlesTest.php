<?php

use App\Jobs\FetchNewsFromSource;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Services\ArticleCategorizationService;
use App\Services\RssFetcherService;
use Illuminate\Support\Collection;

function makeRssEntry(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'entry-'.uniqid('', true),
        'title' => 'Titulo de prueba',
        'snippet' => 'Descripción con suficiente texto.',
        'full_content' => 'Contenido largo del artículo.',
        'image_url' => 'https://example.test/image.jpg',
        'image_urls' => ['https://example.test/image.jpg'],
        'original_url' => 'https://example.test/article',
        'author' => null,
        'published_at' => '2026-04-11T20:00:00+00:00',
    ], $overrides);
}

test('articles without image_url are skipped at ingestion', function () {
    $source = NewsSource::factory()->create();

    $entries = new Collection([
        makeRssEntry(['external_id' => 'valid-1']),
        makeRssEntry(['external_id' => 'no-image', 'image_url' => null, 'image_urls' => []]),
    ]);

    $fetcher = $this->mock(RssFetcherService::class, function ($mock) use ($entries) {
        $mock->shouldReceive('fetch')->once()->andReturn($entries);
    });

    (new FetchNewsFromSource($source))->handle($fetcher, app(ArticleCategorizationService::class));

    expect(NewsArticle::pluck('external_id')->all())->toBe(['valid-1']);
});

test('articles without snippet are skipped at ingestion', function () {
    $source = NewsSource::factory()->create();

    $entries = new Collection([
        makeRssEntry(['external_id' => 'valid-2']),
        makeRssEntry(['external_id' => 'no-snippet', 'snippet' => null]),
        makeRssEntry(['external_id' => 'empty-snippet', 'snippet' => '']),
    ]);

    $fetcher = $this->mock(RssFetcherService::class, function ($mock) use ($entries) {
        $mock->shouldReceive('fetch')->once()->andReturn($entries);
    });

    (new FetchNewsFromSource($source))->handle($fetcher, app(ArticleCategorizationService::class));

    expect(NewsArticle::pluck('external_id')->all())->toBe(['valid-2']);
});

test('valid articles with both image and snippet are stored', function () {
    $source = NewsSource::factory()->create();

    $entries = new Collection([
        makeRssEntry(['external_id' => 'a']),
        makeRssEntry(['external_id' => 'b']),
    ]);

    $fetcher = $this->mock(RssFetcherService::class, function ($mock) use ($entries) {
        $mock->shouldReceive('fetch')->once()->andReturn($entries);
    });

    (new FetchNewsFromSource($source))->handle($fetcher, app(ArticleCategorizationService::class));

    expect(NewsArticle::count())->toBe(2);
});
