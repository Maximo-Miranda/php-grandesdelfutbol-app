<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

test('setting a player as coach detaches them from a sibling team roster', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);

    $bayer = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);
    $manchester = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);

    $eric = Player::factory()->create(['club_id' => $club->id]);
    $manchester->players()->attach($eric->id);

    $bayer->update(['coach_player_id' => $eric->id]);
    $bayer->detachPlayersFromSiblings([$eric->id]);

    expect($manchester->players()->where('players.id', $eric->id)->exists())->toBeFalse()
        ->and($bayer->fresh()->coach_player_id)->toBe($eric->id);
});

test('setting a player as captain clears the same role on a sibling team', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);

    $bayer = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
        'captain_player_id' => null,
    ]);
    $manchester = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);

    $eric = Player::factory()->create(['club_id' => $club->id]);
    $manchester->update(['captain_player_id' => $eric->id]);

    $bayer->update(['captain_player_id' => $eric->id]);
    $bayer->detachPlayersFromSiblings([$eric->id]);

    expect($manchester->fresh()->captain_player_id)->toBeNull()
        ->and($bayer->fresh()->captain_player_id)->toBe($eric->id);
});

test('admin update on non-tournament team detaches coach from sibling rosters', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);

    $bayer = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);
    $manchester = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);

    $eric = Player::factory()->create(['club_id' => $club->id]);
    $manchester->players()->attach($eric->id);
    $manchester->update(['captain_player_id' => $eric->id]);

    $this->actingAs($admin)
        ->put(route('clubs.teams.update', [$club, $bayer]), [
            'name' => $bayer->name,
            'color' => $bayer->color,
            'coach_player_id' => $eric->id,
            'captain_player_id' => $eric->id,
        ])
        ->assertRedirect();

    expect($manchester->players()->where('players.id', $eric->id)->exists())->toBeFalse()
        ->and($manchester->fresh()->captain_player_id)->toBeNull()
        ->and($bayer->fresh()->coach_player_id)->toBe($eric->id)
        ->and($bayer->fresh()->captain_player_id)->toBe($eric->id);
});

test('tournament team enforces exclusivity within the tournament bucket', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);

    $tournamentA = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => true,
    ]);
    $tournamentB = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => true,
    ]);

    $shared = Player::factory()->create(['club_id' => $club->id]);
    $tournamentA->players()->attach($shared->id);

    $tournamentB->players()->attach($shared->id);
    $tournamentB->detachPlayersFromSiblings([$shared->id]);

    expect($tournamentA->players()->where('players.id', $shared->id)->exists())->toBeFalse()
        ->and($tournamentB->players()->where('players.id', $shared->id)->exists())->toBeTrue();
});

test('tournament membership does not affect non-tournament rosters', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);

    $regular = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);
    $tournament = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => true,
    ]);

    $player = Player::factory()->create(['club_id' => $club->id]);
    $regular->players()->attach($player->id);

    $tournament->players()->attach($player->id);
    $tournament->detachPlayersFromSiblings([$player->id]);

    // Joining a tournament team must not remove the player from their non-tournament team.
    expect($regular->players()->where('players.id', $player->id)->exists())->toBeTrue()
        ->and($tournament->players()->where('players.id', $player->id)->exists())->toBeTrue();
});

test('available players list excludes coach and captain of sibling teams', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);

    $manchester = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);
    $bayer = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_tournament' => false,
    ]);

    $coachOfManchester = Player::factory()->create(['club_id' => $club->id]);
    $captainOfManchester = Player::factory()->create(['club_id' => $club->id]);
    $availablePlayer = Player::factory()->create(['club_id' => $club->id]);

    $manchester->update([
        'coach_player_id' => $coachOfManchester->id,
        'captain_player_id' => $captainOfManchester->id,
    ]);

    $response = $this->actingAs($admin)->get(route('clubs.teams.edit', [$club, $bayer]));
    $response->assertOk();

    $players = collect($response->viewData('page')['props']['players'] ?? []);
    $playerIds = $players->pluck('id')->all();

    expect($playerIds)->not->toContain($coachOfManchester->id)
        ->and($playerIds)->not->toContain($captainOfManchester->id)
        ->and($playerIds)->toContain($availablePlayer->id);
});
