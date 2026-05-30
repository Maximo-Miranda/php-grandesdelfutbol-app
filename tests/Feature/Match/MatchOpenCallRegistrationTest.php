<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchService;

test('open call match forces team to null even if payload sends one', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'team' => null,
    ]);
});

test('members cannot register after registration_closes_at even with capacity', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addHours(8),
        'registration_opens_hours' => 24,
        'registration_closes_at' => now()->subMinute(),
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertInvalid(['status']);

    $this->assertDatabaseMissing('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
    ]);
});

test('admins can still register after registration_closes_at', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addHours(8),
        'registration_closes_at' => now()->subMinute(),
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);
});

test('effectiveRegistrationClosesAt falls back to scheduled_at when null', function () {
    $scheduledAt = now()->addDay()->startOfMinute();
    $match = FootballMatch::factory()->create([
        'scheduled_at' => $scheduledAt,
        'registration_closes_at' => null,
    ]);

    expect($match->effectiveRegistrationClosesAt()->toDateTimeString())
        ->toBe($scheduledAt->toDateTimeString());
});

test('isOpenCall returns true when no team rosters are set', function () {
    $match = FootballMatch::factory()->create([
        'team_a_id' => null,
        'team_b_id' => null,
    ]);

    expect($match->isOpenCall())->toBeTrue();
});

test('20 confirmations fill capacity then 21st goes to waitlist', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
        'max_players' => 14,
        'max_substitutes' => 6,
    ]);

    $players = Player::factory()->count(21)->create(['club_id' => $club->id]);

    foreach ($players as $i => $player) {
        $this->actingAs($user)
            ->post(route('clubs.matches.attendance.store', [$club, $match]), [
                'player_id' => $player->id,
                'status' => 'confirmed',
            ])
            ->assertRedirect();

        $this->travel(1)->seconds();
    }

    expect($match->attendances()->where('status', 'confirmed')->count())->toBe(20)
        ->and($match->attendances()->where('status', 'waitlisted')->count())->toBe(1);
});

test('create persists registration_closes_at', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $closesAt = now()->addHours(6)->startOfMinute();

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Open Call Test',
            'scheduled_at' => now()->addHours(11)->toDateTimeString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 14,
            'max_substitutes' => 6,
            'registration_opens_hours' => 24,
            'registration_closes_at' => $closesAt->toDateTimeString(),
            'is_recurring' => false,
            'auto_cancel' => false,
        ])
        ->assertRedirect();

    $match = FootballMatch::query()->where('title', 'Open Call Test')->first();
    expect($match->registration_closes_at)->not->toBeNull()
        ->and($match->registration_closes_at->toDateTimeString())->toBe($closesAt->toDateTimeString());
});

test('recurring match preserves registration_closes_at offset', function () {
    $service = app(MatchService::class);

    $match = FootballMatch::factory()->recurring(7)->completed()->create([
        'scheduled_at' => now()->subDay(),
        'registration_closes_at' => now()->subDays(1)->subHours(5),
    ]);

    $offsetSeconds = abs($match->scheduled_at->diffInSeconds($match->registration_closes_at));

    $newMatch = $service->recreateIfRecurring($match);

    expect($newMatch)->not->toBeNull()
        ->and($newMatch->registration_closes_at)->not->toBeNull();

    $newOffset = abs($newMatch->scheduled_at->diffInSeconds($newMatch->registration_closes_at));

    expect($newOffset)->toBe($offsetSeconds);
});
