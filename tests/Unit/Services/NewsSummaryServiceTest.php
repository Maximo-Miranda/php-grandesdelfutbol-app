<?php

use App\Ai\Agents\SummarizeNewsArticle;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Services\NewsSummaryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Exceptions\RateLimitedException;

beforeEach(function () {
    config()->set('news.ai.per_minute_limit', 10);
    config()->set('news.ai.daily_limit', 20);
    config()->set('news.ai.min_content_length', 200);

    $this->service = app(NewsSummaryService::class);
});

test('summarize returns true when article already has a summary', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => 'Resumen existente.',
    ]);

    expect($this->service->summarize($article))->toBeTrue();
});

test('summarize reuses existing summary from story group', function () {
    SummarizeNewsArticle::fake()->preventStrayPrompts();

    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();
    $storyGroupId = fake()->uuid();

    NewsArticle::factory()->create([
        'news_source_id' => $source1->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => 'Resumen del grupo.',
    ]);

    $article = NewsArticle::factory()->create([
        'news_source_id' => $source2->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeTrue();
    expect($article->fresh()->ai_summary)->toBe('Resumen del grupo.');
});

test('summarize generates new summary when no group summary exists', function () {
    SummarizeNewsArticle::fake(['Resumen generado por IA.']);

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeTrue();
    expect($article->fresh()->ai_summary)->toBe('Resumen generado por IA.');
});

test('summarize propagates summary to all articles in story group', function () {
    SummarizeNewsArticle::fake(['Resumen compartido.']);

    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();
    $storyGroupId = fake()->uuid();

    $article1 = NewsArticle::factory()->create([
        'news_source_id' => $source1->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => null,
    ]);

    $article2 = NewsArticle::factory()->create([
        'news_source_id' => $source2->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => null,
    ]);

    $this->service->summarize($article1);

    expect($article1->fresh()->ai_summary)->toBe('Resumen compartido.');
    expect($article2->fresh()->ai_summary)->toBe('Resumen compartido.');
});

test('summarize returns false when daily limit reached', function () {
    Cache::put('news:ai_summary_count', $this->service->dailyLimit(), now()->endOfDay());

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeFalse();
    expect($article->fresh()->ai_summary)->toBeNull();
});

test('summarize returns false when AI fails', function () {
    SummarizeNewsArticle::fake([
        fn () => throw new RuntimeException('API error'),
    ]);

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeFalse();
    expect($article->fresh()->ai_summary)->toBeNull();
});

test('increments usage counter after successful AI call', function () {
    SummarizeNewsArticle::fake(['Resumen.']);

    expect($this->service->usedToday())->toBe(0);

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    $this->service->summarize($article);

    expect($this->service->usedToday())->toBe(1);
});

test('does not increment usage when reusing group summary', function () {
    SummarizeNewsArticle::fake()->preventStrayPrompts();

    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();
    $storyGroupId = fake()->uuid();

    NewsArticle::factory()->create([
        'news_source_id' => $source1->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => 'Ya existe.',
    ]);

    $article = NewsArticle::factory()->create([
        'news_source_id' => $source2->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => null,
    ]);

    $this->service->summarize($article);

    expect($this->service->usedToday())->toBe(0);
});

test('remaining today reflects usage', function () {
    $limit = $this->service->dailyLimit();

    expect($this->service->remainingToday())->toBe($limit);

    Cache::put('news:ai_summary_count', 5, now()->endOfDay());

    expect($this->service->remainingToday())->toBe($limit - 5);
});

test('remaining today never returns a negative value', function () {
    Cache::put('news:ai_summary_count', $this->service->dailyLimit() + 50, now()->endOfDay());

    expect($this->service->remainingToday())->toBe(0);
});

test('hasEnoughContent returns false when full_content and snippet are empty', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->withoutContent()->create(['news_source_id' => $source->id]);

    expect($this->service->hasEnoughContent($article))->toBeFalse();
});

test('hasEnoughContent returns true when content meets threshold', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'full_content' => str_repeat('a', $this->service->minContentLength()),
    ]);

    expect($this->service->hasEnoughContent($article))->toBeTrue();
});

test('hasEnoughContent returns false for short articles below threshold', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'full_content' => str_repeat('a', $this->service->minContentLength() - 50),
    ]);

    expect($this->service->hasEnoughContent($article))->toBeFalse();
});

test('estimateReadingMinutes scales with content length', function () {
    $source = NewsSource::factory()->create();

    $short = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'full_content' => str_repeat('a', 500),
    ]);

    $long = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'full_content' => str_repeat('a', 5000),
    ]);

    expect($this->service->estimateReadingMinutes($short))->toBe(1);
    expect($this->service->estimateReadingMinutes($long))->toBeGreaterThanOrEqual(4);
});

test('summarize returns false when article has insufficient content', function () {
    SummarizeNewsArticle::fake()->preventStrayPrompts();

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->withoutContent()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeFalse();
});

test('per minute rate limit blocks further generation', function () {
    RateLimiter::clear('gemini-api');

    // Exhaust the per-minute limit
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('gemini-api');
    }

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeFalse();
    expect($article->fresh()->ai_summary)->toBeNull();
});

test('provider rate limit backs off local limiter without incrementing usage', function () {
    RateLimiter::clear('gemini-api');

    SummarizeNewsArticle::fake([
        fn () => throw RateLimitedException::forProvider('gemini', 429),
    ]);

    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeFalse();
    expect($this->service->usedToday())->toBe(0);
    expect(RateLimiter::remaining('gemini-api', 10))->toBe(0);
});

test('story group reuse is not affected by per minute rate limit', function () {
    RateLimiter::clear('gemini-api');

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('gemini-api');
    }

    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();
    $storyGroupId = fake()->uuid();

    NewsArticle::factory()->create([
        'news_source_id' => $source1->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => 'Resumen del grupo.',
    ]);

    $article = NewsArticle::factory()->create([
        'news_source_id' => $source2->id,
        'story_group_id' => $storyGroupId,
        'ai_summary' => null,
    ]);

    expect($this->service->summarize($article))->toBeTrue();
    expect($article->fresh()->ai_summary)->toBe('Resumen del grupo.');
});
