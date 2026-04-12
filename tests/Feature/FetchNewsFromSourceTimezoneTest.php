<?php

use App\Jobs\FetchNewsFromSource;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Services\ArticleCategorizationService;
use App\Services\RssFetcherService;
use Illuminate\Support\Collection;

test('job stores published_at in the app timezone when the feed reports UTC', function () {
    $source = NewsSource::factory()->create();

    // Feed reports 23:30 UTC → in Bogotá (UTC-5) that is 18:30 wall-clock.
    $utcPublishedAt = '2026-04-11T23:30:00+00:00';

    $fetcher = $this->mock(RssFetcherService::class, function ($mock) use ($utcPublishedAt) {
        $mock->shouldReceive('fetch')->once()->andReturn(new Collection([
            [
                'external_id' => 'tz-test-1',
                'title' => 'Timezone fix check',
                'snippet' => 'snippet',
                'full_content' => null,
                'image_url' => 'https://example.test/tz.jpg',
                'image_urls' => ['https://example.test/tz.jpg'],
                'original_url' => 'https://example.test/tz-article',
                'author' => null,
                'published_at' => $utcPublishedAt,
            ],
        ]));
    });

    (new FetchNewsFromSource($source))->handle(
        $fetcher,
        app(ArticleCategorizationService::class),
    );

    $article = NewsArticle::where('external_id', 'tz-test-1')->firstOrFail();

    // Raw DB value should be the Bogotá wall clock (18:30), not UTC 23:30.
    expect($article->getRawOriginal('published_at'))->toBe('2026-04-11 18:30:00');

    // Same instant in time — both wall clocks point to the same UTC moment.
    expect($article->published_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-04-11 23:30:00');
});
