<?php

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('users with no clubs are redirected to club creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('clubs.create'));
});

test('users with no clubs but a pending invitation are redirected to invitation', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['email' => $user->email]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('invitations.show', $invitation->token));
});

test('dashboard renders for users with clubs', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('topClubs', 1)
            ->has('playerStats')
            ->has('pendingInvitations')
            ->has('upcomingMatches.data')
        );
});

test('dashboard includes player stats across clubs', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
        'goals' => 5,
        'assists' => 3,
        'matches_played' => 10,
        'yellow_cards' => 1,
        'red_cards' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('playerStats.goals', 5)
            ->where('playerStats.assists', 3)
            ->where('playerStats.matches', 10)
            ->where('playerStats.yellowCards', 1)
            ->where('playerStats.redCards', 0)
        );
});

test('dashboard shows top 3 clubs by combined activity', function () {
    $user = User::factory()->create();

    $clubs = Club::factory()->count(4)->create();
    foreach ($clubs as $club) {
        ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    }

    // Club 0: matches_count=5, upcoming=3 → score 8
    FootballMatch::factory()->count(2)->completed()->create(['club_id' => $clubs[0]->id]);
    FootballMatch::factory()->count(3)->create(['club_id' => $clubs[0]->id]);

    // Club 1: matches_count=10, upcoming=0 → score 10
    FootballMatch::factory()->count(10)->completed()->create(['club_id' => $clubs[1]->id]);

    // Club 2: matches_count=7, upcoming=6 → score 13 (highest — upcoming activity weighs more)
    FootballMatch::factory()->count(1)->completed()->create(['club_id' => $clubs[2]->id]);
    FootballMatch::factory()->count(6)->create(['club_id' => $clubs[2]->id]);

    // Club 3: 0 matches (should not appear in top 3)

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('topClubs', 3)
            ->where('topClubs.0.id', $clubs[2]->id)
            ->where('topClubs.1.id', $clubs[1]->id)
            ->where('topClubs.2.id', $clubs[0]->id)
        );
});

test('dashboard paginates upcoming matches via scroll', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    FootballMatch::factory()->count(3)->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('upcomingMatches.data', 3)
        );
});

test('dashboard defers recent matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->missing('recentMatches')
        );
});

test('dashboard includes pending invitations for the user', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $otherClub = Club::factory()->create();
    ClubInvitation::factory()->create(['email' => $user->email, 'club_id' => $otherClub->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 1)
        );
});
