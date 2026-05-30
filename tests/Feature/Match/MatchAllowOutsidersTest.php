<?php

use App\Enums\AttendanceStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

function makeRestrictedMatch(Club $club, Season $season, bool $allowOutsiders): array
{
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'allow_outsiders' => $allowOutsiders,
    ]);

    return [$teamA, $teamB, $match];
}

test('outsider is rejected when allow_outsiders is false (default)', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    [$teamA, $teamB, $match] = makeRestrictedMatch($club, $season, allowOutsiders: false);

    $rosterMember = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($rosterMember->id);

    $outsider = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $outsider->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $outsider->id,
    ]);
});

test('outsider joins the pool with team=null when allow_outsiders is true', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    [$teamA, $teamB, $match] = makeRestrictedMatch($club, $season, allowOutsiders: true);

    $rosterMember = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($rosterMember->id);

    $outsider = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $outsider->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $outsider->id,
        'status' => 'confirmed',
        'team' => null,
    ]);
});

test('autoAssign keeps rostered players in their nómina and drafts outsiders between teams', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    [$teamA, $teamB, $match] = makeRestrictedMatch($club, $season, allowOutsiders: true);

    $rosterA = Player::factory()->create(['club_id' => $club->id]);
    $rosterB = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($rosterA->id);
    $teamB->players()->attach($rosterB->id);

    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $rosterA->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $rosterB->id,
    ]);

    $outsiders = Player::factory()->count(2)->create(['club_id' => $club->id]);
    foreach ($outsiders as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'team' => null,
            'confirmed_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $this->actingAs($admin)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    $match->load('attendances');

    expect($match->attendances->where('player_id', $rosterA->id)->first()->team->value)->toBe('a')
        ->and($match->attendances->where('player_id', $rosterB->id)->first()->team->value)->toBe('b')
        ->and($match->attendances->whereNotNull('team')->count())->toBe(4);

    $outsiderTeams = $outsiders->map(fn ($p) => $match->attendances->where('player_id', $p->id)->first()->team?->value);
    expect($outsiderTeams->filter()->count())->toBe(2);
});

test('outsider preference from a previous completed match is honored', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    [$teamA, $teamB, $match] = makeRestrictedMatch($club, $season, allowOutsiders: true);

    // Both rosters need at least one player so the new player is treated as outsider
    // (otherwise resolveTeamForPlayer auto-populates empty rosters).
    $teamA->players()->attach(Player::factory()->create(['club_id' => $club->id])->id);
    $teamB->players()->attach(Player::factory()->create(['club_id' => $club->id])->id);

    $previousMatch = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->subWeek(),
    ]);

    $outsider = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $previousMatch->id,
        'player_id' => $outsider->id,
    ]);

    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $outsider->id,
        'status' => AttendanceStatus::Confirmed,
        'team' => null,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    expect(MatchAttendance::where('player_id', $outsider->id)
        ->where('match_id', $match->id)
        ->first()->team->value)->toBe('b');
});

test('myPlayer is tagged is_outsider when allow_outsiders is true', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    [$teamA, $teamB, $match] = makeRestrictedMatch($club, $season, allowOutsiders: true);

    $myPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('clubs.matches.show', [$club, $match]));

    $response->assertOk();
    $myPlayerProp = $response->viewData('page')['props']['myPlayer'];
    expect($myPlayerProp)->not->toBeNull()
        ->and($myPlayerProp['is_outsider'])->toBeTrue();
});

test('myPlayer is_outsider is false when player is rostered', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    [$teamA, $teamB, $match] = makeRestrictedMatch($club, $season, allowOutsiders: true);

    $myPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $teamA->players()->attach($myPlayer->id);

    $response = $this->actingAs($user)->get(route('clubs.matches.show', [$club, $match]));

    $response->assertOk();
    $myPlayerProp = $response->viewData('page')['props']['myPlayer'];
    expect($myPlayerProp['is_outsider'])->toBeFalse()
        ->and($myPlayerProp['eligible_team'])->toBe('a');
});
