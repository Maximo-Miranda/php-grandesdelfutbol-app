<?php

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;

test('match belongs to a club', function () {
    $match = FootballMatch::factory()->create();
    expect($match->club)->toBeInstanceOf(Club::class);
});

test('match has many attendances', function () {
    $match = FootballMatch::factory()->create();
    MatchAttendance::factory()->count(3)->create(['match_id' => $match->id]);

    expect($match->attendances)->toHaveCount(3);
});

test('match casts status to enum', function () {
    $match = FootballMatch::factory()->create();
    expect($match->status)->toBe(MatchStatus::Upcoming);
});

test('upcoming scope returns only upcoming matches', function () {
    FootballMatch::factory()->create();
    FootballMatch::factory()->completed()->create();

    expect(FootballMatch::query()->upcoming()->count())->toBe(1);
});

test('completed scope returns only completed matches', function () {
    FootballMatch::factory()->create();
    FootballMatch::factory()->completed()->create();

    expect(FootballMatch::query()->completed()->count())->toBe(1);
});

test('match casts datetime fields', function () {
    $match = FootballMatch::factory()->create(['scheduled_at' => now()]);
    expect($match->scheduled_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});
