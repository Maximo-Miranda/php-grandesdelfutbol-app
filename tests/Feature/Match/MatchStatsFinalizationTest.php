<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\User;

test('admin can finalize stats for a completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'goals' => 0, 'assists' => 0]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->assist()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    $match->refresh();
    $player->refresh();

    expect($match->stats_finalized_at)->not->toBeNull()
        ->and($player->goals)->toBe(1)
        ->and($player->assists)->toBe(1)
        ->and($player->matches_played)->toBe(1);
});

test('cannot finalize stats for non-completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('cannot finalize stats twice', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'stats_finalized_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('non-admin cannot finalize stats', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertForbidden();
});
