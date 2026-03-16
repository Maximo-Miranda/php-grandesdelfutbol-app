<?php

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;

test('admin can start an upcoming match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.start', [$club, $match]))
        ->assertRedirect();

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::InProgress)
        ->and($match->started_at)->not->toBeNull();
});

test('cannot start match more than 30 min before scheduled time', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addHours(2),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.start', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error', 'El partido solo puede iniciarse 30 minutos antes.');

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Upcoming);
});

test('can start match within 30 min of scheduled time', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.start', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::InProgress);
});

test('cannot start a match that is not upcoming', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.start', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('admin can complete an in-progress match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.complete', [$club, $match]))
        ->assertRedirect();

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Completed)
        ->and($match->ended_at)->not->toBeNull();
});

test('cannot complete a match that is not in progress', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.complete', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('admin can cancel an upcoming match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.cancel', [$club, $match]))
        ->assertRedirect();

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Cancelled);
});

test('cannot cancel a completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.cancel', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('non-admin cannot start a match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.start', [$club, $match]))
        ->assertForbidden();
});
