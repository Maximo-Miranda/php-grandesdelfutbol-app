<?php

use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $this->club->id, 'user_id' => $this->admin->id]);
});

test('admin can rename an active season', function () {
    $season = Season::factory()->create(['club_id' => $this->club->id, 'name' => 'Temporada #1']);

    $this->actingAs($this->admin)
        ->patch(route('clubs.seasons.update', [$this->club, $season]), ['name' => 'Apertura 2026'])
        ->assertRedirect();

    expect($season->fresh()->name)->toBe('Apertura 2026');
});

test('admin can rename a completed season', function () {
    $season = Season::factory()->completed()->create(['club_id' => $this->club->id, 'name' => 'Old name']);

    $this->actingAs($this->admin)
        ->patch(route('clubs.seasons.update', [$this->club, $season]), ['name' => 'Clausura 2025'])
        ->assertRedirect();

    expect($season->fresh()->name)->toBe('Clausura 2025');
});

test('admin cannot change matches_count on a completed season', function () {
    $season = Season::factory()->completed()->create(['club_id' => $this->club->id, 'matches_count' => 15]);

    $this->actingAs($this->admin)
        ->patch(route('clubs.seasons.update', [$this->club, $season]), ['matches_count' => 11])
        ->assertSessionHasErrors('matches_count');

    expect($season->fresh()->matches_count)->toBe(15);
});

test('closing an active season marks it completed and creates a new active one', function () {
    $season = Season::factory()->create(['club_id' => $this->club->id, 'name' => 'Temporada #1']);

    $this->actingAs($this->admin)
        ->post(route('clubs.seasons.close', [$this->club, $season]))
        ->assertRedirect();

    $fresh = $season->fresh();
    expect($fresh->status)->toBe(SeasonStatus::Completed);
    expect($fresh->completed_at)->not->toBeNull();

    $newSeason = Season::query()->where('club_id', $this->club->id)->where('status', SeasonStatus::Active)->first();
    expect($newSeason)->not->toBeNull();
    expect($newSeason->name)->toBe('Temporada #2');
});

test('matches created after closing go to the new active season', function () {
    $originalSeason = Season::factory()->create(['club_id' => $this->club->id]);

    $this->actingAs($this->admin)->post(route('clubs.seasons.close', [$this->club, $originalSeason]))->assertRedirect();

    $newMatch = FootballMatch::factory()->create(['club_id' => $this->club->id]);

    $newActive = Season::query()->where('club_id', $this->club->id)->where('status', SeasonStatus::Active)->first();
    expect($newMatch->season_id)->toBe($newActive->id);
    expect($newMatch->season_id)->not->toBe($originalSeason->id);
});

test('non-admin members cannot close a season', function () {
    $member = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $this->club->id, 'user_id' => $member->id]);
    $season = Season::factory()->create(['club_id' => $this->club->id]);

    $this->actingAs($member)
        ->post(route('clubs.seasons.close', [$this->club, $season]))
        ->assertForbidden();
});
