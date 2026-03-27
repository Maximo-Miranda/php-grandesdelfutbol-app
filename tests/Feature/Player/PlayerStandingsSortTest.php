<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;

test('standings sort players by total contributions then by goals as tiebreaker', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $playerA = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player A', 'goals' => 2, 'assists' => 0]);
    $playerB = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player B', 'goals' => 1, 'assists' => 1]);
    $playerC = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player C', 'goals' => 3, 'assists' => 0]);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Index')
            ->has('players.data', 3)
            ->where('players.data.0.id', $playerC->id)
            ->where('players.data.1.id', $playerA->id)
            ->where('players.data.2.id', $playerB->id)
        );
});

test('players with same total but more goals rank higher', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $playerA = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player A', 'goals' => 0, 'assists' => 2]);
    $playerB = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player B', 'goals' => 2, 'assists' => 0]);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Index')
            ->where('players.data.0.id', $playerB->id)
            ->where('players.data.1.id', $playerA->id)
        );
});
