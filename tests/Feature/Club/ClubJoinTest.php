<?php

use App\Models\Club;
use App\Models\User;

test('unauthenticated users see join page for active invite links', function () {
    $club = Club::factory()->withInviteActive()->create();

    $this->get(route('clubs.join', $club->invite_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/JoinLink')
            ->has('club')
            ->has('token')
            ->has('requiresApproval')
        );
});

test('unauthenticated users get 404 for inactive invite links', function () {
    $club = Club::factory()->create(['is_invite_active' => false]);

    $this->get(route('clubs.join', $club->invite_token))
        ->assertNotFound();
});

test('authenticated users are auto-joined when visiting join link', function () {
    $club = Club::factory()->withInviteActive()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.join', $club->invite_token))
        ->assertRedirect(route('clubs.show', $club));

    $this->assertDatabaseHas('club_members', [
        'club_id' => $club->id,
        'user_id' => $user->id,
        'status' => 'approved',
    ]);
});

test('authenticated users with approval required get pending status', function () {
    $club = Club::factory()->withInviteActive()->withApproval()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.join', $club->invite_token))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('club_members', [
        'club_id' => $club->id,
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});

test('registration with valid join_token stores token in session and does not auto-verify', function () {
    $club = Club::factory()->withInviteActive()->create();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'join_token' => $club->invite_token,
    ])->assertRedirect();

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect(session('join_token'))->toBe($club->invite_token);
});

test('registration with invalid join_token does not auto-verify email', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'join_token' => 'invalid-token',
    ])->assertRedirect();

    $user = User::query()->where('email', 'test2@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('registration with join_token of inactive link does not auto-verify email', function () {
    $club = Club::factory()->create(['is_invite_active' => false]);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test3@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'join_token' => $club->invite_token,
    ])->assertRedirect();

    $user = User::query()->where('email', 'test3@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('join page shows requires approval badge', function () {
    $club = Club::factory()->withInviteActive()->withApproval()->create();

    $this->get(route('clubs.join', $club->invite_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/JoinLink')
            ->where('requiresApproval', true)
        );
});

test('pending members appear on invite page', function () {
    $club = Club::factory()->withInviteActive()->withApproval()->create();
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
    $club = Club::factory()->withInviteActive()->create();

    $this->post(route('clubs.join.store', $club->invite_token))
        ->assertRedirect(route('login'));
});
