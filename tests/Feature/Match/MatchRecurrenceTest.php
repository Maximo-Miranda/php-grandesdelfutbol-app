<?php

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Services\MatchService;

test('auto-completion recreates recurring match', function () {
    $match = FootballMatch::factory()->recurring(7)->create([
        'status' => MatchStatus::InProgress,
        'auto_started' => true,
        'started_at' => now()->subMinutes(90),
        'scheduled_at' => now()->subMinutes(90),
        'duration_minutes' => 60,
    ]);

    $this->artisan('matches:process-schedules')->assertSuccessful();

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::Completed)
        ->and($match->next_match_created_at)->not->toBeNull();

    $newMatch = FootballMatch::query()
        ->where('club_id', $match->club_id)
        ->where('status', MatchStatus::Upcoming)
        ->where('id', '!=', $match->id)
        ->first();

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->scheduled_at->toDateString())
        ->toBe($match->scheduled_at->addDays(7)->toDateString())
        ->and($newMatch->is_recurring)->toBeTrue()
        ->and($newMatch->recurrence_days)->toBe(7);
});

test('auto-completion does not recreate non-recurring match', function () {
    $match = FootballMatch::factory()->create([
        'is_recurring' => false,
        'status' => MatchStatus::InProgress,
        'auto_started' => true,
        'started_at' => now()->subMinutes(90),
        'duration_minutes' => 60,
    ]);

    $countBefore = FootballMatch::count();

    $this->artisan('matches:process-schedules')->assertSuccessful();

    expect(FootballMatch::count())->toBe($countBefore)
        ->and($match->refresh()->next_match_created_at)->toBeNull();
});

test('manual completion recreates recurring match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $match = FootballMatch::factory()->recurring(15)->inProgress()->create([
        'club_id' => $club->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.complete', [$club, $match]))
        ->assertRedirect();

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Completed)
        ->and($match->next_match_created_at)->not->toBeNull();

    $newMatch = FootballMatch::query()
        ->where('club_id', $club->id)
        ->where('status', MatchStatus::Upcoming)
        ->where('id', '!=', $match->id)
        ->first();

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->recurrence_days)->toBe(15);
});

test('cancelled non-recurring match does not recreate', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'is_recurring' => false,
        'status' => MatchStatus::Upcoming,
        'scheduled_at' => now()->addMinutes(10),
    ]);

    $countBefore = FootballMatch::count();

    $this->actingAs($user)
        ->post(route('clubs.matches.cancel', [$club, $match]))
        ->assertRedirect();

    expect(FootballMatch::count())->toBe($countBefore)
        ->and($match->refresh()->next_match_created_at)->toBeNull();
});

test('duplicate prevention via next_match_created_at', function () {
    $match = FootballMatch::factory()->recurring()->completed()->create([
        'next_match_created_at' => now(),
    ]);

    $countBefore = FootballMatch::count();

    $service = app(MatchService::class);
    $result = $service->recreateIfRecurring($match);

    expect($result)->toBeNull()
        ->and(FootballMatch::count())->toBe($countBefore);
});

test('past scheduled_at advances to future', function () {
    $match = FootballMatch::factory()->recurring(7)->completed()->create([
        'scheduled_at' => now()->subDays(30),
    ]);

    $service = app(MatchService::class);
    $newMatch = $service->recreateIfRecurring($match);

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->scheduled_at->isFuture())->toBeTrue();
});

test('new match copies correct fields and generates new identifiers', function () {
    $match = FootballMatch::factory()->recurring(7)->completed()->create([
        'duration_minutes' => 90,
        'arrival_minutes' => 20,
        'max_players' => 14,
        'max_substitutes' => 6,
        'registration_opens_hours' => 48,
        'notes' => 'Traer agua',
        'team_a_name' => 'Eq. Rojo',
        'team_b_name' => 'Eq. Azul',
        'team_a_color' => '#dc2626',
        'team_b_color' => '#2563eb',
    ]);

    $service = app(MatchService::class);
    $newMatch = $service->recreateIfRecurring($match);

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->club_id)->toBe($match->club_id)
        ->and($newMatch->field_id)->toBe($match->field_id)
        ->and($newMatch->duration_minutes)->toBe(90)
        ->and($newMatch->arrival_minutes)->toBe(20)
        ->and($newMatch->max_players)->toBe(14)
        ->and($newMatch->max_substitutes)->toBe(6)
        ->and($newMatch->registration_opens_hours)->toBe(48)
        ->and($newMatch->notes)->toBe('Traer agua')
        ->and($newMatch->team_a_name)->toBe('Eq. Rojo')
        ->and($newMatch->team_b_name)->toBe('Eq. Azul')
        ->and($newMatch->team_a_color)->toBe('#dc2626')
        ->and($newMatch->team_b_color)->toBe('#2563eb')
        ->and($newMatch->is_recurring)->toBeTrue()
        ->and($newMatch->recurrence_days)->toBe(7)
        ->and($newMatch->status)->toBe(MatchStatus::Upcoming)
        ->and($newMatch->ulid)->not->toBe($match->ulid)
        ->and($newMatch->share_token)->not->toBe($match->share_token)
        ->and($newMatch->title)->not->toBe($match->title)
        ->and($newMatch->started_at)->toBeNull()
        ->and($newMatch->ended_at)->toBeNull()
        ->and($newMatch->stats_finalized_at)->toBeNull()
        ->and($newMatch->next_match_created_at)->toBeNull()
        ->and($newMatch->fresh()->auto_started)->toBeFalse();
});

test('validation rejects recurrence_days out of range', function () {
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
        'is_recurring' => true,
    ];

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), array_merge($baseData, ['recurrence_days' => 0]))
        ->assertSessionHasErrors('recurrence_days');

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), array_merge($baseData, ['recurrence_days' => 91]))
        ->assertSessionHasErrors('recurrence_days');

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), array_merge($baseData, ['recurrence_days' => 45]))
        ->assertSessionDoesntHaveErrors('recurrence_days');
});

test('create and update persist recurrence fields', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Recurring Match',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => true,
            'recurrence_days' => 15,
        ])
        ->assertRedirect();

    $match = FootballMatch::query()->where('title', 'Recurring Match')->first();
    expect($match->is_recurring)->toBeTrue()
        ->and($match->recurrence_days)->toBe(15);

    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => 'Recurring Match',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => false,
            'recurrence_days' => 30,
        ])
        ->assertRedirect();

    expect($match->refresh()->is_recurring)->toBeFalse()
        ->and($match->recurrence_days)->toBe(30);
});

test('creating match with past date auto-recreates next match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Past Recurring',
            'scheduled_at' => now()->subDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'is_recurring' => true,
            'recurrence_days' => 7,
            'auto_cancel' => false,
            'min_players_required' => 10,
        ])
        ->assertRedirect();

    $match = FootballMatch::query()->where('title', 'Past Recurring')->first();
    expect($match->status)->toBe(MatchStatus::Completed)
        ->and($match->next_match_created_at)->not->toBeNull();

    $nextMatch = FootballMatch::query()
        ->where('club_id', $club->id)
        ->where('status', MatchStatus::Upcoming)
        ->where('id', '!=', $match->id)
        ->first();

    expect($nextMatch)->not->toBeNull()
        ->and($nextMatch->scheduled_at->isFuture())->toBeTrue()
        ->and($nextMatch->is_recurring)->toBeTrue()
        ->and($nextMatch->recurrence_days)->toBe(7);
});
