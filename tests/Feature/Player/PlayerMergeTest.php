<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerMergeService;

test('admin can merge players when assigning user with existing player', function () {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $targetUser->id]);

    $sourcePlayer = Player::factory()->create(['club_id' => $club->id, 'goals' => 5]);
    $targetPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $targetUser->id, 'goals' => 2]);

    $this->actingAs($admin)
        ->put(route('clubs.players.update', [$club, $sourcePlayer]), [
            'name' => $sourcePlayer->name,
            'user_id' => $targetUser->id,
            'is_active' => true,
        ])
        ->assertRedirect(route('clubs.players.show', [$club, $targetPlayer]));

    $this->assertDatabaseMissing('players', ['id' => $sourcePlayer->id]);
    expect($targetPlayer->fresh()->goals)->toBe(7);
});

test('merge sums all stat columns correctly', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();

    $source = Player::factory()->create([
        'club_id' => $club->id,
        'goals' => 5, 'assists' => 3, 'matches_played' => 10,
        'yellow_cards' => 2, 'red_cards' => 1, 'fouls' => 4, 'saves' => 6, 'handballs' => 1,
        'own_goals' => 2, 'penalties_scored' => 3, 'penalties_missed' => 1,
    ]);
    $target = Player::factory()->create([
        'club_id' => $club->id, 'user_id' => $user->id,
        'goals' => 2, 'assists' => 1, 'matches_played' => 5,
        'yellow_cards' => 1, 'red_cards' => 0, 'fouls' => 2, 'saves' => 3, 'handballs' => 0,
        'own_goals' => 1, 'penalties_scored' => 1, 'penalties_missed' => 0,
    ]);

    $merged = app(PlayerMergeService::class)->merge($source, $target);

    expect($merged->goals)->toBe(7)
        ->and($merged->assists)->toBe(4)
        ->and($merged->matches_played)->toBe(0) // recalculated from attendances, not summed
        ->and($merged->yellow_cards)->toBe(3)
        ->and($merged->red_cards)->toBe(1)
        ->and($merged->fouls)->toBe(6)
        ->and($merged->saves)->toBe(9)
        ->and($merged->handballs)->toBe(1)
        ->and($merged->own_goals)->toBe(3)
        ->and($merged->penalties_scored)->toBe(4)
        ->and($merged->penalties_missed)->toBe(1);
});

test('merge recalculates matches_played from actual attendances', function () {
    $club = Club::factory()->create();

    // 3 completed matches
    $matchA = FootballMatch::factory()->create(['club_id' => $club->id, 'status' => 'completed']);
    $matchB = FootballMatch::factory()->create(['club_id' => $club->id, 'status' => 'completed']);
    $matchC = FootballMatch::factory()->create(['club_id' => $club->id, 'status' => 'completed']);
    // 1 upcoming match (should not count)
    $matchD = FootballMatch::factory()->create(['club_id' => $club->id, 'status' => 'upcoming']);

    $source = Player::factory()->create(['club_id' => $club->id, 'matches_played' => 2]);
    $target = Player::factory()->create(['club_id' => $club->id, 'matches_played' => 2, 'user_id' => User::factory()->create()->id]);

    // Source attended A and B, target attended B and C (B is shared)
    MatchAttendance::factory()->create(['match_id' => $matchA->id, 'player_id' => $source->id]);
    MatchAttendance::factory()->create(['match_id' => $matchB->id, 'player_id' => $source->id]);
    MatchAttendance::factory()->create(['match_id' => $matchB->id, 'player_id' => $target->id]);
    MatchAttendance::factory()->create(['match_id' => $matchC->id, 'player_id' => $target->id]);
    // Upcoming match should not count
    MatchAttendance::factory()->create(['match_id' => $matchD->id, 'player_id' => $source->id]);

    $merged = app(PlayerMergeService::class)->merge($source, $target);

    // A + B + C = 3 completed matches (not 2+2=4, and not counting upcoming matchD)
    expect($merged->matches_played)->toBe(3);
});

test('merge transfers match events to target player', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $source = Player::factory()->create(['club_id' => $club->id]);
    $target = Player::factory()->create(['club_id' => $club->id, 'user_id' => User::factory()->create()->id]);

    $event = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $source->id]);

    app(PlayerMergeService::class)->merge($source, $target);

    expect($event->fresh()->player_id)->toBe($target->id);
});

test('merge transfers non-conflicting match attendances', function () {
    $club = Club::factory()->create();
    $matchA = FootballMatch::factory()->create(['club_id' => $club->id]);
    $matchB = FootballMatch::factory()->create(['club_id' => $club->id]);

    $source = Player::factory()->create(['club_id' => $club->id]);
    $target = Player::factory()->create(['club_id' => $club->id, 'user_id' => User::factory()->create()->id]);

    MatchAttendance::factory()->create(['match_id' => $matchA->id, 'player_id' => $source->id]);
    MatchAttendance::factory()->create(['match_id' => $matchB->id, 'player_id' => $target->id]);

    app(PlayerMergeService::class)->merge($source, $target);

    expect(MatchAttendance::where('player_id', $target->id)->count())->toBe(2);
});

test('merge handles conflicting match attendances', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $source = Player::factory()->create(['club_id' => $club->id]);
    $target = Player::factory()->create(['club_id' => $club->id, 'user_id' => User::factory()->create()->id]);

    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $source->id]);
    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $target->id]);

    app(PlayerMergeService::class)->merge($source, $target);

    expect(MatchAttendance::where('match_id', $match->id)->where('player_id', $target->id)->count())->toBe(1)
        ->and(MatchAttendance::where('match_id', $match->id)->where('player_id', $source->id)->count())->toBe(0);
});

test('merge adopts position from source when target has none', function () {
    $club = Club::factory()->create();

    $source = Player::factory()->create(['club_id' => $club->id, 'position' => 'ST']);
    $target = Player::factory()->create(['club_id' => $club->id, 'position' => null, 'user_id' => User::factory()->create()->id]);

    $merged = app(PlayerMergeService::class)->merge($source, $target);

    expect($merged->position->value)->toBe('ST');
});

test('merge preserves target position over source', function () {
    $club = Club::factory()->create();

    $source = Player::factory()->create(['club_id' => $club->id, 'position' => 'ST']);
    $target = Player::factory()->create(['club_id' => $club->id, 'position' => 'GK', 'user_id' => User::factory()->create()->id]);

    $merged = app(PlayerMergeService::class)->merge($source, $target);

    expect($merged->position->value)->toBe('GK');
});

test('merge adopts jersey number from source when target has none', function () {
    $club = Club::factory()->create();

    $source = Player::factory()->create(['club_id' => $club->id, 'jersey_number' => 10]);
    $target = Player::factory()->create(['club_id' => $club->id, 'jersey_number' => null, 'user_id' => User::factory()->create()->id]);

    $merged = app(PlayerMergeService::class)->merge($source, $target);

    expect($merged->jersey_number)->toBe(10);
});

test('update without merge still works normally', function () {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $targetUser->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => $player->name,
            'user_id' => $targetUser->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($player->fresh()->user_id)->toBe($targetUser->id);
});

test('source player is deleted after merge', function () {
    $club = Club::factory()->create();

    $source = Player::factory()->create(['club_id' => $club->id, 'goals' => 3]);
    $target = Player::factory()->create(['club_id' => $club->id, 'user_id' => User::factory()->create()->id]);

    app(PlayerMergeService::class)->merge($source, $target);

    $this->assertDatabaseMissing('players', ['id' => $source->id]);
    $this->assertDatabaseHas('players', ['id' => $target->id]);
});
