<?php

use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;

test('standings sort players by goals desc then matches asc', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $playerA = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player A', 'goals' => 2, 'matches_played' => 5, 'position' => PlayerPosition::St]);
    $playerB = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player B', 'goals' => 0, 'matches_played' => 3, 'position' => PlayerPosition::Cm]);
    $playerC = Player::factory()->create(['club_id' => $club->id, 'name' => 'Player C', 'goals' => 3, 'matches_played' => 2, 'position' => PlayerPosition::Cf]);

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

test('players with same goals rank by fewer matches first', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $manyMatches = Player::factory()->create(['club_id' => $club->id, 'name' => 'Many Matches', 'goals' => 5, 'matches_played' => 10, 'position' => PlayerPosition::St]);
    $fewMatches = Player::factory()->create(['club_id' => $club->id, 'name' => 'Few Matches', 'goals' => 5, 'matches_played' => 3, 'position' => PlayerPosition::Cf]);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Index')
            ->where('players.data.0.id', $fewMatches->id)
            ->where('players.data.1.id', $manyMatches->id)
        );
});

test('goalkeepers are separated from the main standings', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $fieldPlayer = Player::factory()->create(['club_id' => $club->id, 'name' => 'Field Player', 'goals' => 3, 'position' => PlayerPosition::St]);
    $goalkeeper = Player::factory()->create(['club_id' => $club->id, 'name' => 'Goalkeeper', 'saves' => 10, 'position' => PlayerPosition::Gk]);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Index')
            ->has('players.data', 1)
            ->where('players.data.0.id', $fieldPlayer->id)
            ->has('goalkeepers', 1)
            ->where('goalkeepers.0.id', $goalkeeper->id)
        );
});

test('goalkeepers are sorted by saves desc then matches asc', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $gkA = Player::factory()->create(['club_id' => $club->id, 'name' => 'GK A', 'saves' => 5, 'matches_played' => 10, 'position' => PlayerPosition::Gk]);
    $gkB = Player::factory()->create(['club_id' => $club->id, 'name' => 'GK B', 'saves' => 8, 'matches_played' => 3, 'position' => PlayerPosition::Gk]);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Index')
            ->has('goalkeepers', 2)
            ->where('goalkeepers.0.id', $gkB->id)
            ->where('goalkeepers.1.id', $gkA->id)
        );
});
