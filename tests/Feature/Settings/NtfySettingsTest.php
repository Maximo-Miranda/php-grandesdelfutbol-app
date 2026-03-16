<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access ntfy settings', function () {
    $this->get(route('ntfy.edit'))->assertRedirect(route('login'));
});

test('ntfy settings page renders for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ntfy.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Notifications')
            ->has('ntfyTopic')
            ->has('ntfyEnabled')
            ->has('ntfyUrl')
            ->where('ntfyEnabled', false),
        );
});

test('ntfy settings page shows enabled state when ntfy is configured', function () {
    $user = User::factory()->withNtfy()->create();

    $this->actingAs($user)
        ->get(route('ntfy.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ntfyEnabled', true),
        );
});

test('test notification can be sent', function () {
    Http::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ntfy.test'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Notificación de prueba enviada.');
});

test('setup can be confirmed', function () {
    $user = User::factory()->create();

    expect($user->hasNtfyEnabled())->toBeFalse();

    $this->actingAs($user)
        ->post(route('ntfy.confirm'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Notificaciones push habilitadas.');

    expect($user->fresh()->hasNtfyEnabled())->toBeTrue();
});

test('ntfy can be disabled', function () {
    $user = User::factory()->withNtfy()->create();

    expect($user->hasNtfyEnabled())->toBeTrue();

    $this->actingAs($user)
        ->post(route('ntfy.disable'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Notificaciones push deshabilitadas.');

    expect($user->fresh()->hasNtfyEnabled())->toBeFalse();
});

test('ntfy token is generated automatically when creating a user', function () {
    $user = User::factory()->create();

    expect($user->ntfy_token)->toBeString()
        ->and(strlen($user->ntfy_token))->toBe(26);
});

test('ntfy topic includes the token', function () {
    $user = User::factory()->create();

    expect($user->ntfyTopic())->toBe("gdf-{$user->ntfy_token}");
});

test('ntfy enabled is shared via inertia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ntfy.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ntfyEnabled', false),
        );

    $user->update(['ntfy_enabled_at' => now()]);

    $this->actingAs($user->fresh())
        ->get(route('ntfy.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ntfyEnabled', true),
        );
});
