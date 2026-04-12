<?php

use App\Ai\Agents\SummarizeNewsArticle;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Models\User;
use Laravel\Ai\Ai;

test('guests cannot summarize articles', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

    $this->post(route('news.summarize', $article))
        ->assertRedirect(route('login'));
});

test('summarize generates and caches AI summary', function () {
    Ai::fakeAgent(SummarizeNewsArticle::class, [
        'Resumen generado por IA del artículo de prueba.',
    ]);

    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    $this->actingAs($user)
        ->post(route('news.summarize', $article))
        ->assertRedirect();

    $article->refresh();
    expect($article->ai_summary)->toBe('Resumen generado por IA del artículo de prueba.');
});

test('summarize skips AI when summary is already cached', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => 'Resumen existente.',
    ]);

    $this->actingAs($user)
        ->post(route('news.summarize', $article))
        ->assertRedirect();

    $article->refresh();
    expect($article->ai_summary)->toBe('Resumen existente.');
});

test('summarize rejects articles without enough content', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->withoutContent()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    $this->actingAs($user)
        ->post(route('news.summarize', $article))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($article->fresh()->ai_summary)->toBeNull();
});

test('summarize handles AI failure gracefully', function () {
    Ai::fakeAgent(SummarizeNewsArticle::class, [
        fn () => throw new RuntimeException('API error'),
    ]);

    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create([
        'news_source_id' => $source->id,
        'ai_summary' => null,
    ]);

    $this->actingAs($user)
        ->post(route('news.summarize', $article))
        ->assertRedirect()
        ->assertSessionHas('error');

    $article->refresh();
    expect($article->ai_summary)->toBeNull();
});
