<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;

test('non-members cannot view matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.matches.index', $club))
        ->assertForbidden();
});

test('regular members cannot create matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Test',
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
        ->assertForbidden();
});

test('regular members cannot update matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), ['title' => 'Hack', 'scheduled_at' => now()->toISOString()])
        ->assertForbidden();
});
