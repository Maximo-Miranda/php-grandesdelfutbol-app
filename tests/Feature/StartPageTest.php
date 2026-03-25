<?php

use App\Models\User;

test('start page can be rendered with default register mode', function () {
    $response = $this->get(route('start'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Start')
        ->has('canRegister')
        ->where('mode', 'register')
        ->has('googleAuthEnabled')
    );
});

test('start page can be rendered in login mode', function () {
    $response = $this->get(route('start', ['mode' => 'login']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Start')
        ->where('mode', 'login')
    );
});

test('authenticated users are redirected from start page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('start'));

    $response->assertRedirect();
});
