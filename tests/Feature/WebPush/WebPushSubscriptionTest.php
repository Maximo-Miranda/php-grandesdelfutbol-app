<?php

use App\Models\User;

test('guests cannot subscribe to web push', function () {
    $this->postJson(route('web-push.store'), [
        'endpoint' => 'https://push.example.com/1',
        'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
    ])->assertUnauthorized();
});

test('authenticated user can subscribe to web push', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('web-push.store'), [
            'endpoint' => 'https://push.example.com/1',
            'keys' => ['p256dh' => 'BNcRdreALRFX', 'auth' => 'tBHItJ'],
            'content_encoding' => 'aesgcm',
        ])
        ->assertCreated();

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('authenticated user can update existing subscription', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/1', 'old_key', 'old_auth');

    $this->actingAs($user)
        ->postJson(route('web-push.store'), [
            'endpoint' => 'https://push.example.com/1',
            'keys' => ['p256dh' => 'new_key', 'auth' => 'new_auth'],
        ])
        ->assertCreated();

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('authenticated user can unsubscribe from web push', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/1', 'key', 'auth');

    expect($user->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($user)
        ->deleteJson(route('web-push.destroy'), [
            'endpoint' => 'https://push.example.com/1',
        ])
        ->assertOk();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('subscribe requires valid endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('web-push.store'), [
            'endpoint' => 'not-a-url',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});

test('subscribe requires keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('web-push.store'), [
            'endpoint' => 'https://push.example.com/1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

test('vapid public key is shared via inertia', function () {
    config(['webpush.vapid.public_key' => 'test-vapid-key']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('vapidPublicKey', 'test-vapid-key'));
});
