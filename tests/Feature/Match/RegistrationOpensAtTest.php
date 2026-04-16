<?php

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchRegistrationOpenNotification;
use App\Services\MatchService;
use Illuminate\Support\Facades\Notification;

test('effectiveRegistrationOpensAt returns manual value when set', function () {
    $opensAt = now()->addDay()->startOfMinute();
    $match = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(3),
        'registration_opens_hours' => 24,
        'registration_opens_at' => $opensAt,
    ]);

    expect($match->effectiveRegistrationOpensAt()->toDateTimeString())
        ->toBe($opensAt->toDateTimeString());
});

test('effectiveRegistrationOpensAt falls back to hours calculation when null', function () {
    $match = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(3),
        'registration_opens_hours' => 24,
        'registration_opens_at' => null,
    ]);

    $expected = now()->addDays(3)->subHours(24);

    expect($match->effectiveRegistrationOpensAt()->toDateTimeString())
        ->toBe($expected->toDateTimeString());
});

test('isRegistrationOpen respects manual registration_opens_at', function () {
    $service = app(MatchService::class);

    $match = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(3),
        'registration_opens_hours' => 24,
        'registration_opens_at' => now()->subHour(),
    ]);

    expect($service->isRegistrationOpen($match))->toBeTrue();
});

test('isRegistrationOpen respects future manual registration_opens_at', function () {
    $service = app(MatchService::class);

    $match = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(3),
        'registration_opens_hours' => 24,
        'registration_opens_at' => now()->addDay(),
    ]);

    expect($service->isRegistrationOpen($match))->toBeFalse();
});

test('notify command sends notification when manual registration_opens_at has passed', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addDays(5),
        'registration_opens_hours' => 24,
        'registration_opens_at' => now()->subMinutes(5),
        'registration_notified_at' => null,
    ]);

    $this->artisan('matches:notify-registration-open')->assertSuccessful();

    Notification::assertSentTo($user, MatchRegistrationOpenNotification::class);
});

test('notify command does not send notification when manual registration_opens_at is in future', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addDays(5),
        'registration_opens_hours' => 24,
        'registration_opens_at' => now()->addDays(2),
        'registration_notified_at' => null,
    ]);

    $this->artisan('matches:notify-registration-open')->assertSuccessful();

    Notification::assertNothingSent();
});

test('booted hook resets registration_notified_at when registration_opens_at changes to future', function () {
    $match = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(3),
        'registration_opens_at' => now()->subHour(),
        'registration_notified_at' => now(),
    ]);

    $match->update(['registration_opens_at' => now()->addDay()]);

    expect($match->refresh()->registration_notified_at)->toBeNull();
});

test('booted hook does not reset registration_notified_at when registration_opens_at stays in past', function () {
    $match = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(3),
        'registration_opens_at' => now()->subHours(2),
        'registration_notified_at' => now(),
    ]);

    $match->update(['registration_opens_at' => now()->subHour()]);

    expect($match->refresh()->registration_notified_at)->not->toBeNull();
});

test('recurring match preserves registration_opens_at offset', function () {
    $service = app(MatchService::class);

    $match = FootballMatch::factory()->recurring(7)->completed()->create([
        'scheduled_at' => now()->subDay(),
        'registration_opens_at' => now()->subDays(3)->subHours(9),
    ]);

    $offsetSeconds = abs($match->scheduled_at->diffInSeconds($match->registration_opens_at));

    $newMatch = $service->recreateIfRecurring($match);

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->registration_opens_at)->not->toBeNull();

    $newOffset = abs($newMatch->scheduled_at->diffInSeconds($newMatch->registration_opens_at));

    expect($newOffset)->toBe($offsetSeconds)
        ->and($newMatch->registration_opens_at->lt($newMatch->scheduled_at))->toBeTrue();
});

test('recurring match leaves registration_opens_at null when original is null', function () {
    $service = app(MatchService::class);

    $match = FootballMatch::factory()->recurring(7)->completed()->create([
        'scheduled_at' => now()->subDay(),
        'registration_opens_at' => null,
    ]);

    $newMatch = $service->recreateIfRecurring($match);

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->registration_opens_at)->toBeNull();
});

test('create and update persist registration_opens_at', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $opensAt = now()->addDay()->startOfHour();

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Manual Registration Test',
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'registration_opens_at' => $opensAt->toDateTimeString(),
            'is_recurring' => false,
            'auto_cancel' => false,
        ])
        ->assertRedirect();

    $match = FootballMatch::query()->where('title', 'Manual Registration Test')->first();
    expect($match->registration_opens_at)->not->toBeNull()
        ->and($match->registration_opens_at->toDateTimeString())->toBe($opensAt->toDateTimeString());
});

test('validation rejects registration_opens_at after scheduled_at', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Invalid Registration Test',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'registration_opens_at' => now()->addDays(5)->toISOString(),
            'is_recurring' => false,
            'auto_cancel' => false,
        ])
        ->assertSessionHasErrors('registration_opens_at');
});

test('validation rejects cancel_hours_before greater than registration opening (auto mode)', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Cancel Before Registration',
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => false,
            'auto_cancel' => true,
            'min_players_required' => 6,
            'cancel_hours_before' => 48,
        ])
        ->assertSessionHasErrors('cancel_hours_before');
});

test('validation rejects cancel_hours_before greater than registration opening (manual mode)', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $scheduledAt = now()->addDays(3);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Cancel Before Registration Manual',
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'registration_opens_at' => $scheduledAt->copy()->subHours(12)->toDateTimeString(),
            'is_recurring' => false,
            'auto_cancel' => true,
            'min_players_required' => 6,
            'cancel_hours_before' => 24,
        ])
        ->assertSessionHasErrors('cancel_hours_before');
});

test('validation accepts cancel_hours_before within registration window', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Cancel Within Window',
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 48,
            'is_recurring' => false,
            'auto_cancel' => true,
            'min_players_required' => 6,
            'cancel_hours_before' => 10,
        ])
        ->assertSessionDoesntHaveErrors('cancel_hours_before');
});

test('effectiveCancelHoursBefore returns custom value when set', function () {
    $match = FootballMatch::factory()->withCancelHours(5)->create();

    expect($match->effectiveCancelHoursBefore())->toBe(5);
});

test('effectiveCancelHoursBefore returns default 10 when null', function () {
    $match = FootballMatch::factory()->create(['cancel_hours_before' => null]);

    expect($match->effectiveCancelHoursBefore())->toBe(10);
});

test('auto-cancel respects custom cancel_hours_before', function () {
    Notification::fake();

    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'cancel_hours_before' => 5,
        'scheduled_at' => now()->addHours(4),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled);
});

test('auto-cancel does not trigger outside custom cancel_hours_before window', function () {
    $match = FootballMatch::factory()->create([
        'status' => MatchStatus::Upcoming,
        'auto_cancel' => true,
        'min_players_required' => 10,
        'cancel_hours_before' => 3,
        'scheduled_at' => now()->addHours(4),
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect($match->refresh()->status)->toBe(MatchStatus::Upcoming);
});

test('recurring match copies cancel_hours_before', function () {
    $service = app(MatchService::class);

    $match = FootballMatch::factory()->recurring(7)->completed()->create([
        'scheduled_at' => now()->subDay(),
        'cancel_hours_before' => 5,
    ]);

    $newMatch = $service->recreateIfRecurring($match);

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->cancel_hours_before)->toBe(5);
});

test('create and update persist cancel_hours_before', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Cancel Hours Test',
            'scheduled_at' => now()->addDays(3)->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => false,
            'auto_cancel' => true,
            'min_players_required' => 6,
            'cancel_hours_before' => 8,
        ])
        ->assertRedirect();

    $match = FootballMatch::query()->where('title', 'Cancel Hours Test')->first();
    expect($match->cancel_hours_before)->toBe(8);
});
