<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

test('myPlayer is tagged with eligible_team on team-restricted match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $myPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $teamA->players()->attach($myPlayer->id);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
    ]);

    $response = $this->actingAs($user)->get(route('clubs.matches.show', [$club, $match]));

    $response->assertOk();
    $myPlayerProp = $response->viewData('page')['props']['myPlayer'];
    expect($myPlayerProp)->not->toBeNull()
        ->and($myPlayerProp['eligible_team'])->toBe('a');
});

test('myPlayer eligible_team follows the roster on a tournament match, never either', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id, 'is_tournament' => true]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id, 'is_tournament' => true]);

    $myPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $teamA->players()->attach($myPlayer->id);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
    ]);

    $response = $this->actingAs($user)->get(route('clubs.matches.show', [$club, $match]));

    $response->assertOk();
    // Pinned to their team, so the frontend skips the "Elige tu equipo" modal.
    expect($response->viewData('page')['props']['myPlayer']['eligible_team'])->toBe('a');
});

test('myPlayer eligible_team is null on open call match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $myPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('clubs.matches.show', [$club, $match]));

    $response->assertOk();
    $myPlayerProp = $response->viewData('page')['props']['myPlayer'];
    expect($myPlayerProp)->not->toBeNull()
        ->and($myPlayerProp['eligible_team'] ?? null)->toBeNull();
});
