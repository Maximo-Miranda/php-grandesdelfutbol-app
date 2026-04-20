<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('members can view the standings page', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.standings.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clubs/standings/Index'));
});

test('non-members are forbidden from the standings page', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.standings.index', $club))
        ->assertForbidden();
});

test('standings respects the tab query param', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.standings.index', $club).'?tab=players')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tab', 'players'));
});
