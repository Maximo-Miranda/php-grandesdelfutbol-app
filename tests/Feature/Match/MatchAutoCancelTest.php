<?php

use App\Enums\AttendanceStatus;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('auto-cancels match 10h before when no confirmed players', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(9),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled);
});

test('does not auto-cancel when at least one player confirmed', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(9),
    ]);

    $player = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => AttendanceStatus::Confirmed,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Upcoming);
});

test('does not auto-cancel when enough players confirmed', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 4,
        'scheduled_at' => now()->addHours(9),
    ]);

    foreach (range(1, 5) as $i) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Upcoming);
});

test('does not auto-cancel when auto_cancel is disabled', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => false,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(9),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Upcoming);
});

test('does not auto-cancel match more than 10h before scheduled time', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(15),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Upcoming);
});

test('does not auto-cancel match that already passed scheduled time', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->subMinutes(5),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::InProgress);
});

test('notifies confirmed players when match is auto-cancelled', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(5),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled);

    Notification::assertNothingSent();
});

test('auto-cancel runs before auto-start so cancelled matches are not started', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addMinutes(30),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled);
});

test('auto-cancelled recurring match recreates next occurrence', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->recurring(7)->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(5),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled)
        ->and($match->next_match_created_at)->not->toBeNull();

    $nextMatch = FootballMatch::query()
        ->where('club_id', $club->id)
        ->where('status', MatchStatus::Upcoming)
        ->where('id', '!=', $match->id)
        ->first();

    expect($nextMatch)->not->toBeNull()
        ->and($nextMatch->is_recurring)->toBeTrue()
        ->and($nextMatch->recurrence_days)->toBe(7)
        ->and($nextMatch->auto_cancel)->toBeTrue();
});

test('manual cancel of recurring match recreates next occurrence', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $match = FootballMatch::factory()->recurring(7)->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.cancel', [$club, $match]))
        ->assertRedirect();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled)
        ->and($match->next_match_created_at)->not->toBeNull();

    $nextMatch = FootballMatch::query()
        ->where('club_id', $club->id)
        ->where('status', MatchStatus::Upcoming)
        ->where('id', '!=', $match->id)
        ->first();

    expect($nextMatch)->not->toBeNull()
        ->and($nextMatch->is_recurring)->toBeTrue();
});

test('create and update persist auto_cancel fields', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Auto Cancel Match',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 14,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => false,
            'auto_cancel' => true,
            'min_players_required' => 8,
        ])
        ->assertRedirect();

    $match = FootballMatch::query()->where('title', 'Auto Cancel Match')->first();
    expect($match->auto_cancel)->toBeTrue()
        ->and($match->min_players_required)->toBe(8);

    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => 'Auto Cancel Match',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 14,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => false,
            'auto_cancel' => false,
            'min_players_required' => 6,
        ])
        ->assertRedirect();

    expect($match->refresh()->auto_cancel)->toBeFalse()
        ->and($match->min_players_required)->toBe(6);
});

test('validation rejects min_players_required out of range', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $baseData = [
        'title' => 'Test Match',
        'scheduled_at' => now()->addDay()->toISOString(),
        'duration_minutes' => 60,
        'arrival_minutes' => 15,
        'max_players' => 10,
        'max_substitutes' => 4,
        'registration_opens_hours' => 24,
        'is_recurring' => false,
        'auto_cancel' => true,
    ];

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), array_merge($baseData, ['min_players_required' => 1]))
        ->assertSessionHasErrors('min_players_required');

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), array_merge($baseData, ['min_players_required' => 51]))
        ->assertSessionHasErrors('min_players_required');

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), array_merge($baseData, ['min_players_required' => 8]))
        ->assertSessionDoesntHaveErrors('min_players_required');
});

test('declined players do not prevent auto-cancel', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'scheduled_at' => now()->addHours(5),
    ]);

    // Only declined players — should still auto-cancel
    foreach (range(1, 3) as $i) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Declined,
        ]);
    }

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled);
});
