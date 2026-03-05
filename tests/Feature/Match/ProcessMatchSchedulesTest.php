<?php

use App\Enums\MatchStatus;
use App\Models\FootballMatch;

test('auto-starts upcoming matches past their scheduled time', function () {
    $pastMatch = FootballMatch::factory()->create([
        'scheduled_at' => now()->subMinutes(10),
        'status' => MatchStatus::Upcoming,
        'duration_minutes' => 60,
    ]);

    $futureMatch = FootballMatch::factory()->create([
        'scheduled_at' => now()->addHour(),
        'status' => MatchStatus::Upcoming,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    $pastMatch->refresh();
    $futureMatch->refresh();

    expect($pastMatch->status)->toBe(MatchStatus::InProgress)
        ->and($pastMatch->auto_started)->toBeTrue()
        ->and($pastMatch->started_at)->not->toBeNull()
        ->and($futureMatch->status)->toBe(MatchStatus::Upcoming)
        ->and($futureMatch->auto_started)->toBeFalse();
});

test('auto-completes auto-started matches after duration ends', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::InProgress,
        'auto_started' => true,
        'started_at' => now()->subMinutes(90),
        'duration_minutes' => 60,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::Completed)
        ->and($match->ended_at)->not->toBeNull();
});

test('does not auto-complete manually started matches', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::InProgress,
        'auto_started' => false,
        'started_at' => now()->subMinutes(90),
        'duration_minutes' => 60,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::InProgress)
        ->and($match->ended_at)->toBeNull();
});

test('does not auto-complete auto-started matches still in progress', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::InProgress,
        'auto_started' => true,
        'started_at' => now()->subMinutes(30),
        'duration_minutes' => 60,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::InProgress)
        ->and($match->ended_at)->toBeNull();
});

test('does not affect completed or cancelled matches', function () {
    $completed = FootballMatch::factory()->completed()->create([
        'scheduled_at' => now()->subDays(2),
    ]);

    $cancelled = FootballMatch::factory()->cancelled()->create([
        'scheduled_at' => now()->subDays(2),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($completed->refresh()->status)->toBe(MatchStatus::Completed)
        ->and($cancelled->refresh()->status)->toBe(MatchStatus::Cancelled);
});

test('sets started_at to scheduled_at when auto-starting', function () {
    $scheduledAt = now()->subMinutes(30);

    $match = FootballMatch::factory()->create([
        'scheduled_at' => $scheduledAt,
        'status' => MatchStatus::Upcoming,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    $match->refresh();

    expect($match->started_at->timestamp)->toBe($scheduledAt->timestamp);
});
