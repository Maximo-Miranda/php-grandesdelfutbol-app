<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('terms page can be rendered', function () {
    $this->get(route('terms'))
        ->assertOk();
});

test('privacy page can be rendered', function () {
    $this->get(route('privacy'))
        ->assertOk();
});

test('registration requires terms acceptance', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('terms');

    $this->assertGuest();
});

test('registration fails when terms is false', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => false,
    ])->assertSessionHasErrors('terms');

    $this->assertGuest();
});

test('registration succeeds when terms is accepted', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ])->assertRedirect();

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user->terms_accepted_at)->not->toBeNull();
});

test('registration stores terms acceptance metadata', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'meta@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ])->assertRedirect();

    $user = User::query()->where('email', 'meta@example.com')->first();
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->terms_accepted_ip)->not->toBeNull();
    expect($user->terms_accepted_user_agent)->not->toBeNull();
});

test('google oauth registration sets terms_accepted_at with metadata', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-terms',
        'name' => 'Google User',
        'email' => 'google-terms@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->withSession(['google_terms_accepted' => true])
        ->get('/auth/google/callback');

    $user = User::query()->where('email', 'google-terms@example.com')->first();
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->terms_accepted_ip)->not->toBeNull();
    expect($user->terms_accepted_user_agent)->not->toBeNull();
});
