<?php

use App\Models\User;
use App\Models\YouTubeToken;
use App\Services\YouTubeService;

use function Pest\Laravel\mock;

it('blocks non-super-admin from youtube authorize', function () {
    $user = User::factory()->create(['email' => 'regular@example.com']);

    $this->actingAs($user)
        ->get(route('youtube.authorize'))
        ->assertForbidden();
});

it('redirects super admin to google oauth', function () {
    config(['app.super_admin_emails' => ['admin@test.com']]);

    $user = User::factory()->create(['email' => 'admin@test.com']);

    mock(YouTubeService::class)
        ->shouldReceive('getAuthUrl')
        ->once()
        ->andReturn('https://accounts.google.com/o/oauth2/auth?test=1');

    $this->actingAs($user)
        ->get(route('youtube.authorize'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth?test=1');
});

it('blocks non-super-admin from youtube callback', function () {
    $user = User::factory()->create(['email' => 'regular@example.com']);

    $this->actingAs($user)
        ->get(route('youtube.callback', ['code' => 'test-code']))
        ->assertForbidden();
});

it('handles youtube oauth callback and stores token', function () {
    config(['app.super_admin_emails' => ['admin@test.com']]);

    $user = User::factory()->create(['email' => 'admin@test.com']);

    mock(YouTubeService::class)
        ->shouldReceive('handleCallback')
        ->with('test-auth-code')
        ->once();

    $this->actingAs($user)
        ->get(route('youtube.callback', ['code' => 'test-auth-code']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');
});

it('redirects to dashboard with error when callback has no code', function () {
    config(['app.super_admin_emails' => ['admin@test.com']]);

    $user = User::factory()->create(['email' => 'admin@test.com']);

    $this->actingAs($user)
        ->get(route('youtube.callback'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
});

it('handles youtube callback failure gracefully', function () {
    config(['app.super_admin_emails' => ['admin@test.com']]);

    $user = User::factory()->create(['email' => 'admin@test.com']);

    mock(YouTubeService::class)
        ->shouldReceive('handleCallback')
        ->andThrow(new RuntimeException('OAuth failed'));

    $this->actingAs($user)
        ->get(route('youtube.callback', ['code' => 'bad-code']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
});

it('creates and retrieves youtube token', function () {
    YouTubeToken::create(['token' => ['access_token' => 'test', 'refresh_token' => 'refresh']]);

    $token = YouTubeToken::current();

    expect($token)->not->toBeNull()
        ->and($token->token)->toBeArray()
        ->and($token->token['access_token'])->toBe('test')
        ->and($token->token['refresh_token'])->toBe('refresh');
});

it('reports youtube as configured when token exists', function () {
    YouTubeToken::create(['token' => ['access_token' => 'test']]);

    $service = new YouTubeService;

    expect($service->isConfigured())->toBeTrue();
});

it('reports youtube as not configured when no token', function () {
    $service = new YouTubeService;

    expect($service->isConfigured())->toBeFalse();
});
