<?php

use App\Enums\AttendanceTeam;
use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchService;

test('admin can swap two confirmed players atomically', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $sourcePlayer = Player::factory()->create(['club_id' => $club->id]);
    $targetPlayer = Player::factory()->create(['club_id' => $club->id]);

    $source = MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $sourcePlayer->id,
    ]);
    $target = MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $targetPlayer->id,
    ]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.attendance.swap', [$club, $match]), [
            'source_attendance_id' => $source->id,
            'target_attendance_id' => $target->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($source->refresh()->team)->toBe(AttendanceTeam::B)
        ->and($target->refresh()->team)->toBe(AttendanceTeam::A);
});

test('swap rejects pairs already in the same team', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $a = MatchAttendance::factory()->teamA()->starter()->create(['match_id' => $match->id]);
    $b = MatchAttendance::factory()->teamA()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.attendance.swap', [$club, $match]), [
            'source_attendance_id' => $a->id,
            'target_attendance_id' => $b->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($a->refresh()->team)->toBe(AttendanceTeam::A)
        ->and($b->refresh()->team)->toBe(AttendanceTeam::A);
});

test('non admins cannot swap', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $member->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $a = MatchAttendance::factory()->teamA()->starter()->create(['match_id' => $match->id]);
    $b = MatchAttendance::factory()->teamB()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($member)
        ->post(route('clubs.matches.attendance.swap', [$club, $match]), [
            'source_attendance_id' => $a->id,
            'target_attendance_id' => $b->id,
        ])
        ->assertForbidden();
});

test('open call match blocks direct team override via update', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
    ]);

    $attendance = MatchAttendance::factory()->teamA()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($admin)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'team' => 'b',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($attendance->refresh()->team)->toBe(AttendanceTeam::A);
});

test('recommendSwapCandidates orders by same position group then closest score', function () {
    $service = app(MatchService::class);

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $sourcePlayer = Player::factory()->create([
        'club_id' => $club->id,
        'position' => PlayerPosition::Cm,
        'matches_played' => 10,
        'goals' => 5,
        'assists' => 3,
    ]);

    $sameGroupPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'position' => PlayerPosition::Cam,
        'matches_played' => 10,
        'goals' => 4,
        'assists' => 4,
    ]);

    $diffGroupPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'position' => PlayerPosition::St,
        'matches_played' => 10,
        'goals' => 5,
        'assists' => 3,
    ]);

    $source = MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $sourcePlayer->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $sameGroupPlayer->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $diffGroupPlayer->id,
    ]);

    $candidates = $service->recommendSwapCandidates($source);

    expect($candidates)->toHaveCount(2)
        ->and($candidates->first()['attendance']->player_id)->toBe($sameGroupPlayer->id)
        ->and($candidates->first()['same_position_group'])->toBeTrue()
        ->and($candidates->first()['recommended'])->toBeTrue();
});

test('swap is rejected once match has started', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $a = MatchAttendance::factory()->teamA()->starter()->create(['match_id' => $match->id]);
    $b = MatchAttendance::factory()->teamB()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.attendance.swap', [$club, $match]), [
            'source_attendance_id' => $a->id,
            'target_attendance_id' => $b->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($a->refresh()->team->value)->toBe('a')
        ->and($b->refresh()->team->value)->toBe('b');
});

test('swap candidates endpoint returns ranked list', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $sourcePlayer = Player::factory()->create(['club_id' => $club->id, 'position' => PlayerPosition::Cm]);
    $candidatePlayer = Player::factory()->create(['club_id' => $club->id, 'position' => PlayerPosition::Cam]);

    $source = MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $sourcePlayer->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $candidatePlayer->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('clubs.matches.attendance.swapCandidates', [$club, $match, $source]))
        ->assertOk()
        ->assertJsonStructure([
            'candidates' => [
                ['attendance_id', 'player_id', 'player_name', 'position', 'score', 'recommended', 'same_position_group'],
            ],
        ])
        ->assertJsonPath('candidates.0.player_id', $candidatePlayer->id);
});
