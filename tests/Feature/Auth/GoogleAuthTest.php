<?php

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('redirect sends user to google oauth', function () {
    Socialite::fake('google');

    $this->get('/auth/google')
        ->assertRedirect();
});

test('redirect stores invite_token in session', function () {
    Socialite::fake('google');

    $this->get('/auth/google?invite_token=abc123')
        ->assertRedirect()
        ->assertSessionHas('google_invite_token', 'abc123');
});

test('redirect stores join_token in session', function () {
    Socialite::fake('google');

    $this->get('/auth/google?join_token=xyz789')
        ->assertRedirect()
        ->assertSessionHas('google_join_token', 'xyz789');
});

test('redirect stores terms_accepted in session', function () {
    Socialite::fake('google');

    $this->get('/auth/google?terms_accepted=1')
        ->assertRedirect()
        ->assertSessionHas('google_terms_accepted', true);
});

test('callback creates new user when terms accepted', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->withSession(['google_terms_accepted' => true])
        ->get('/auth/google/callback')
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $this->assertDatabaseHas('social_accounts', [
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user->password)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->playerProfile)->not->toBeNull();
});

test('callback redirects to register when new user has no terms accepted', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-no-terms',
        'name' => 'No Terms User',
        'email' => 'noterms@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('register'));

    $this->assertDatabaseMissing('users', ['email' => 'noterms@example.com']);
    $this->assertGuest();
});

test('callback stores terms acceptance metadata', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-meta',
        'name' => 'Meta User',
        'email' => 'meta@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->withSession(['google_terms_accepted' => true])
        ->get('/auth/google/callback');

    $user = User::query()->where('email', 'meta@example.com')->first();
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->terms_accepted_ip)->not->toBeNull();
    expect($user->terms_accepted_user_agent)->not->toBeNull();
});

test('callback links google account to existing user by email', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    $user = User::factory()->create(['email' => 'existing@example.com']);
    PlayerProfile::factory()->create(['user_id' => $user->id]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-456',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('home'));

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-456',
    ]);

    $this->assertAuthenticated();
});

test('callback logs in user already linked with google', function () {
    $user = User::factory()->withGoogle('google-789')->create();

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-789',
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('callback auto-verifies email of unverified user', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    $user = User::factory()->unverified()->create(['email' => 'unverified@example.com']);
    PlayerProfile::factory()->create(['user_id' => $user->id]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-unv',
        'name' => $user->name,
        'email' => 'unverified@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->get('/auth/google/callback');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
});

test('callback creates player profile with people api data', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response([
            'genders' => [['value' => 'male']],
            'birthdays' => [['date' => ['year' => 1990, 'month' => 5, 'day' => 15]]],
            'phoneNumbers' => [['value' => '+1234567890']],
        ]),
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-profile',
        'name' => 'Profile User',
        'email' => 'profile@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->withSession(['google_terms_accepted' => true])
        ->get('/auth/google/callback');

    $user = User::query()->where('email', 'profile@example.com')->first();
    $profile = $user->playerProfile;

    expect($profile->gender)->toBe(\App\Enums\Gender::Male);
    expect($profile->date_of_birth->format('Y-m-d'))->toBe('1990-05-15');
    expect($profile->phone)->toBe('+1234567890');
});

test('callback handles invite_token from session', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    $invitation = ClubInvitation::factory()->create([
        'email' => 'invited@example.com',
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-invite',
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->withSession([
        'google_invite_token' => $invitation->token,
        'google_terms_accepted' => true,
    ])->get('/auth/google/callback');

    $this->assertDatabaseHas('club_members', [
        'club_id' => $invitation->club_id,
    ]);

    $invitation->refresh();
    expect($invitation->status->value)->toBe('accepted');
});

test('callback handles join_token from session', function () {
    Http::fake([
        'people.googleapis.com/*' => Http::response(['genders' => [], 'birthdays' => [], 'phoneNumbers' => []]),
    ]);

    $club = Club::factory()->create([
        'is_invite_active' => true,
        'requires_approval' => false,
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-join',
        'name' => 'Join User',
        'email' => 'join@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $this->withSession([
        'google_join_token' => $club->invite_token,
        'google_terms_accepted' => true,
    ])->get('/auth/google/callback');

    $this->assertDatabaseHas('club_members', [
        'club_id' => $club->id,
    ]);
});

test('callback redirects to login if oauth fails', function () {
    Socialite::fake('google');

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'));
});

test('authenticated users cannot access google auth routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/auth/google')
        ->assertRedirect();
});
