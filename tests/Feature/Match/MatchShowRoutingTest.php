<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;

test('admin sees Live page when match is in_progress', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clubs/matches/Live'));
});

test('non-admin sees Show page when match is in_progress', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clubs/matches/Show'));
});

test('everyone sees Summary page when match is completed', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clubs/matches/Summary'));
});

test('admin sees Summary page with isAdmin prop when match is completed', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('isAdmin', true)
        );
});

test('upcoming match renders Show page', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clubs/matches/Show'));
});

test('cancelled match renders Show page', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->cancelled()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clubs/matches/Show'));
});
