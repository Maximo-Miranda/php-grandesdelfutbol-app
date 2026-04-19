<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

function setupTeamMatch(): array
{
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->forSeason($season)->create(['name' => 'Negros']);
    $teamB = Team::factory()->forSeason($season)->create(['name' => 'Amarillos']);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
    ]);

    return compact('user', 'club', 'season', 'teamA', 'teamB', 'match');
}

test('team-restricted match: roster member can confirm and gets auto-assigned to their team', function () {
    ['user' => $user, 'club' => $club, 'teamA' => $teamA, 'match' => $match] = setupTeamMatch();
    $player = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($player->id);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $attendance = $match->attendances()->where('player_id', $player->id)->first();
    expect($attendance->status)->toBe(AttendanceStatus::Confirmed);
    expect($attendance->team)->toBe(AttendanceTeam::A);
});

test('team-restricted match: player not in any team is rejected', function () {
    ['user' => $user, 'club' => $club, 'teamA' => $teamA, 'teamB' => $teamB, 'match' => $match] = setupTeamMatch();

    // Both teams have rosters (non-empty)
    $teamA->players()->attach(Player::factory()->create(['club_id' => $club->id])->id);
    $teamB->players()->attach(Player::factory()->create(['club_id' => $club->id])->id);

    $outsider = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $outsider->id,
            'status' => 'confirmed',
        ])
        ->assertSessionHas('error');

    expect($match->attendances()->where('player_id', $outsider->id)->where('status', AttendanceStatus::Confirmed)->exists())->toBeFalse();
});

test('team-restricted match with empty rosters: first confirmations auto-populate team A', function () {
    ['user' => $user, 'club' => $club, 'teamA' => $teamA, 'match' => $match] = setupTeamMatch();
    // Both teams empty initially
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $attendance = $match->attendances()->where('player_id', $player->id)->first();
    expect($attendance->team)->toBe(AttendanceTeam::A); // empty roster auto-populated A
    expect($teamA->fresh()->players->pluck('id')->all())->toContain($player->id);
});

test('team-restricted match: admin cannot reassign player to opposite team', function () {
    ['user' => $user, 'club' => $club, 'teamA' => $teamA, 'match' => $match] = setupTeamMatch();
    $player = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($player->id);

    // Player confirms — auto goes to team A
    $this->actingAs($user)->post(route('clubs.matches.attendance.store', [$club, $match]), [
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    $attendance = $match->attendances()->where('player_id', $player->id)->first();

    // Admin tries to reassign to team B
    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'team' => 'b',
        ])
        ->assertSessionHas('error');

    expect($attendance->fresh()->team)->toBe(AttendanceTeam::A); // unchanged
});

test('confirming attendance to a different team detaches player from old team in same season', function () {
    ['user' => $user, 'club' => $club, 'teamA' => $teamA, 'teamB' => $teamB, 'match' => $match] = setupTeamMatch();
    $player = Player::factory()->create(['club_id' => $club->id]);

    // Player initially in team B
    $teamB->players()->attach($player->id);

    // Admin updates teamA roster to include this player (manual move)
    $this->actingAs($user)
        ->patch(route('clubs.teams.update', [$club, $teamA]), [
            'name' => $teamA->name,
            'color' => $teamA->color,
            'player_ids' => [$player->id],
        ])
        ->assertRedirect();

    expect($teamA->fresh()->players->pluck('id')->all())->toContain($player->id);
    expect($teamB->fresh()->players->pluck('id')->all())->not->toContain($player->id);
});

test('attachPlayerExclusively removes player from sibling teams', function () {
    ['club' => $club, 'teamA' => $teamA, 'teamB' => $teamB] = setupTeamMatch();
    $player = Player::factory()->create(['club_id' => $club->id]);

    $teamA->players()->attach($player->id);
    expect($teamA->fresh()->players->pluck('id')->all())->toContain($player->id);

    $teamB->attachPlayerExclusively($player->id);

    expect($teamB->fresh()->players->pluck('id')->all())->toContain($player->id);
    expect($teamA->fresh()->players->pluck('id')->all())->not->toContain($player->id);
});

test('non-restricted match: works as before with manual team selection', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    // Match with no team_a_id/team_b_id (free-text mode)
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $match->updateQuietly(['team_a_id' => null, 'team_b_id' => null]);

    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'b',
        ])
        ->assertRedirect();

    $attendance = $match->attendances()->where('player_id', $player->id)->first();
    expect($attendance->team)->toBe(AttendanceTeam::B); // honored manual choice
});
