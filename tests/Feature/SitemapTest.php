<?php

use App\Models\Club;
use App\Models\NewsArticle;

test('sitemap responds with xml content-type', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8');
});

test('sitemap includes core public urls', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $body = $response->getContent();

    expect($body)
        ->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<urlset')
        ->toContain('/terms')
        ->toContain('/privacy')
        ->not->toContain('/news');
});

test('sitemap includes club slugs', function () {
    Club::factory()->create(['slug' => 'los-pibes', 'is_public' => true]);

    $body = $this->get('/sitemap.xml')->getContent();

    expect($body)->toContain('/club/los-pibes');
});

test('sitemap excludes non-public clubs', function () {
    Club::factory()->create(['slug' => 'club-privado', 'is_public' => false]);

    $body = $this->get('/sitemap.xml')->getContent();

    expect($body)->not->toContain('/club/club-privado');
});

test('sitemap excludes news article slugs', function () {
    NewsArticle::factory()->create([
        'slug' => 'gol-de-messi',
        'published_at' => now()->subDay(),
    ]);

    $body = $this->get('/sitemap.xml')->getContent();

    expect($body)->not->toContain('/news/gol-de-messi');
});
