<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\User;

test('adding event to finalized match auto-refreshes player stats', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'goals' => 0]);

    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    // Finalize stats — player should have 1 goal
    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]));

    $player->refresh();
    expect($player->goals)->toBe(1);

    // Add another goal AFTER finalization — stats should auto-refresh
    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 30,
        ])
        ->assertRedirect();

    $player->refresh();
    expect($player->goals)->toBe(2)
        ->and($player->matches_played)->toBe(1);
});

test('editing event on finalized match auto-refreshes player stats', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $playerA = Player::factory()->create(['club_id' => $club->id, 'goals' => 0]);
    $playerB = Player::factory()->create(['club_id' => $club->id, 'goals' => 0]);

    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $playerA->id, 'status' => 'confirmed']);
    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $playerB->id, 'status' => 'confirmed']);

    $event = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $playerA->id]);

    // Finalize — playerA has 1 goal
    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]));

    // Reassign the goal to playerB via PATCH
    $this->actingAs($user)
        ->patch(route('clubs.matches.events.update', [$club, $match, $event]), [
            'player_id' => $playerB->id,
        ])
        ->assertRedirect();

    $playerA->refresh();
    $playerB->refresh();

    expect($playerA->goals)->toBe(0)
        ->and($playerB->goals)->toBe(1);
});

test('full update on finalized match auto-refreshes player stats', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'goals' => 0, 'assists' => 0]);

    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $player->id, 'status' => 'confirmed']);

    $event = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    // Finalize — player has 1 goal
    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]));

    // Change event type from goal to assist via PUT
    $this->actingAs($user)
        ->put(route('clubs.matches.events.fullUpdate', [$club, $match, $event]), [
            'event_type' => 'assist',
            'player_id' => $player->id,
            'minute' => $event->minute,
            'second' => $event->second,
        ])
        ->assertRedirect();

    $player->refresh();
    expect($player->goals)->toBe(0)
        ->and($player->assists)->toBe(1);
});

test('deleting event from finalized match auto-refreshes player stats', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'goals' => 0]);

    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $player->id, 'status' => 'confirmed']);

    $event = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    // Finalize — player has 2 goals
    $this->actingAs($user)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]));

    $player->refresh();
    expect($player->goals)->toBe(2);

    // Delete one goal — should auto-refresh to 1
    $this->actingAs($user)
        ->delete(route('clubs.matches.events.destroy', [$club, $match, $event]))
        ->assertRedirect();

    $player->refresh();
    expect($player->goals)->toBe(1)
        ->and($player->matches_played)->toBe(1);
});

test('events on non-finalized match do not trigger stat refresh', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'goals' => 0]);

    // Add event WITHOUT prior finalization — should NOT update player stats
    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 10,
        ])
        ->assertRedirect();

    $player->refresh();
    expect($player->goals)->toBe(0)
        ->and($match->stats_finalized_at)->toBeNull();
});
