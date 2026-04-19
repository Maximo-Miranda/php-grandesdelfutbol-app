<?php

use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Models\User;

test('admin can rename an active season', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id, 'name' => 'Temporada #1']);

    $this->actingAs($user)
        ->patch(route('clubs.seasons.update', [$club, $season]), ['name' => 'Apertura 2026'])
        ->assertRedirect();

    expect($season->fresh()->name)->toBe('Apertura 2026');
});

test('admin can rename a completed season', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->completed()->create(['club_id' => $club->id, 'name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('clubs.seasons.update', [$club, $season]), ['name' => 'Clausura 2025'])
        ->assertRedirect();

    expect($season->fresh()->name)->toBe('Clausura 2025');
});

test('admin cannot change matches_count on a completed season', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->completed()->create(['club_id' => $club->id, 'matches_count' => 15]);

    $this->actingAs($user)
        ->patch(route('clubs.seasons.update', [$club, $season]), ['matches_count' => 11])
        ->assertSessionHas('error');

    expect($season->fresh()->matches_count)->toBe(15);
});

test('closing an active season marks it completed and creates a new active one', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id, 'name' => 'Temporada #1']);

    $this->actingAs($user)
        ->post(route('clubs.seasons.close', [$club, $season]))
        ->assertRedirect();

    expect($season->fresh()->status)->toBe(SeasonStatus::Completed);
    expect($season->fresh()->completed_at)->not->toBeNull();

    $newSeason = Season::query()->where('club_id', $club->id)->where('status', SeasonStatus::Active)->first();
    expect($newSeason)->not->toBeNull();
    expect($newSeason->name)->toBe('Temporada #2');
});

test('matches created after closing go to the new active season', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $originalSeason = Season::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)->post(route('clubs.seasons.close', [$club, $originalSeason]))->assertRedirect();

    $newMatch = FootballMatch::factory()->create(['club_id' => $club->id]);

    $newActive = Season::query()->where('club_id', $club->id)->where('status', SeasonStatus::Active)->first();
    expect($newMatch->season_id)->toBe($newActive->id);
    expect($newMatch->season_id)->not->toBe($originalSeason->id);
});

test('non-admin members cannot close a season', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $season = Season::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.seasons.close', [$club, $season]))
        ->assertForbidden();
});
