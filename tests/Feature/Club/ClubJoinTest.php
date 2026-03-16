<?php

use App\Models\Club;
use App\Models\User;

test('unauthenticated users see join page', function () {
    $club = Club::factory()->create();

    $this->get(route('clubs.join', $club->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/JoinLink')
            ->has('club')
            ->has('slug')
        );
});

test('unauthenticated users get 404 for invalid slug', function () {
    $this->get(route('clubs.join', 'non-existent-slug'))
        ->assertNotFound();
});

test('authenticated users see pending page when joining via link', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.join', $club->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/JoinPending')
            ->has('club')
            ->where('isNewRequest', true)
        );

    $this->assertDatabaseHas('club_members', [
        'club_id' => $club->id,
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});

test('authenticated users see pending page when revisiting join link', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();

    // First visit creates the pending member
    $this->actingAs($user)->get(route('clubs.join', $club->slug));

    // Second visit shows existing pending state
    $this->actingAs($user)
        ->get(route('clubs.join', $club->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/JoinPending')
            ->where('isNewRequest', false)
        );
});

test('registration with valid join_slug stores slug in session and does not auto-verify', function () {
    $club = Club::factory()->create();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
        'join_slug' => $club->slug,
    ])->assertRedirect();

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect(session('join_slug'))->toBe($club->slug);
});

test('registration with invalid join_slug does not auto-verify email', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
        'join_slug' => 'invalid-slug',
    ])->assertRedirect();

    $user = User::query()->where('email', 'test2@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('pending members appear on invite page', function () {
    $club = Club::factory()->create();
    $admin = User::factory()->create();
    $club->members()->create([
        'user_id' => $admin->id,
        'role' => 'owner',
        'status' => 'approved',
        'approved_at' => now(),
    ]);

    // Create a pending member
    $pendingUser = User::factory()->create();
    $club->members()->create([
        'user_id' => $pendingUser->id,
        'role' => 'player',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('clubs.invitations.create', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Invite')
            ->has('pendingMembers', 1)
        );
});

test('post join route requires authentication', function () {
    $club = Club::factory()->create();

    $this->post(route('clubs.join.store', $club->slug))
        ->assertRedirect(route('login'));
});
