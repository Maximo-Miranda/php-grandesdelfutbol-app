<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;

test('attendance belongs to a match', function () {
    $attendance = MatchAttendance::factory()->create();
    expect($attendance->match)->toBeInstanceOf(FootballMatch::class);
});

test('attendance belongs to a player', function () {
    $attendance = MatchAttendance::factory()->create();
    expect($attendance->player)->toBeInstanceOf(Player::class);
});

test('attendance casts status to enum', function () {
    $attendance = MatchAttendance::factory()->create();
    expect($attendance->status)->toBe(AttendanceStatus::Confirmed);
});

test('attendance casts role to enum', function () {
    $attendance = MatchAttendance::factory()->create(['role' => AttendanceRole::Starter]);
    expect($attendance->role)->toBe(AttendanceRole::Starter);
});

test('attendance casts team to enum', function () {
    $attendance = MatchAttendance::factory()->teamA()->create();
    expect($attendance->team)->toBe(AttendanceTeam::A);
});
