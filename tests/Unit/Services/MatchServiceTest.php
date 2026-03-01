<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Services\MatchService;

test('createMatch creates a match with defaults', function () {
    $club = Club::factory()->create();
    $service = new MatchService;

    $match = $service->createMatch($club, [
        'title' => 'Sunday Match',
        'scheduled_at' => now()->addDay()->toISOString(),
    ]);

    expect($match->title)->toBe('Sunday Match')
        ->and($match->club_id)->toBe($club->id)
        ->and($match->status)->toBe(MatchStatus::Upcoming)
        ->and($match->share_token)->not->toBeNull()
        ->and($match->duration_minutes)->toBe(60)
        ->and($match->max_players)->toBe(10);
});

test('registerPlayer creates or updates attendance', function () {
    $match = FootballMatch::factory()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $service = new MatchService;

    $attendance = $service->registerPlayer($match, $player, AttendanceStatus::Confirmed);

    expect($attendance->status)->toBe(AttendanceStatus::Confirmed)
        ->and($attendance->confirmed_at)->not->toBeNull();

    // Update to declined
    $attendance = $service->registerPlayer($match, $player, AttendanceStatus::Declined);
    expect($attendance->status)->toBe(AttendanceStatus::Declined)
        ->and($attendance->confirmed_at)->toBeNull();

    // Should not duplicate
    expect(MatchAttendance::query()->where('match_id', $match->id)->count())->toBe(1);
});

test('isRegistrationOpen checks status and timing', function () {
    $service = new MatchService;

    $upcomingMatch = FootballMatch::factory()->create([
        'scheduled_at' => now()->addHours(12),
        'registration_opens_hours' => 24,
    ]);
    expect($service->isRegistrationOpen($upcomingMatch))->toBeTrue();

    $completedMatch = FootballMatch::factory()->completed()->create();
    expect($service->isRegistrationOpen($completedMatch))->toBeFalse();

    $futureMatch = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(5),
        'registration_opens_hours' => 24,
    ]);
    expect($service->isRegistrationOpen($futureMatch))->toBeFalse();
});

test('autoAssignTeams distributes players into teams', function () {
    $match = FootballMatch::factory()->create(['max_players' => 6]);
    $players = Player::factory()->count(6)->create(['club_id' => $match->club_id]);

    foreach ($players as $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    $service = new MatchService;
    $service->autoAssignTeams($match);

    $match->load('attendances');
    $teamA = $match->attendances->where('team', 'a')->count();
    $teamB = $match->attendances->where('team', 'b')->count();

    expect($teamA)->toBe(3)
        ->and($teamB)->toBe(3);

    $starters = $match->attendances->where('role', AttendanceRole::Starter)->count();
    expect($starters)->toBe(6);
});
