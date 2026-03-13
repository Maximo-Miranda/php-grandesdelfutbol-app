<?php

use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Services\MatchStatService;

test('finalize stats updates player goals from events', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'goals' => 0]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(2);
});

test('finalize stats updates assists and cards', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'assists' => 0, 'yellow_cards' => 0, 'red_cards' => 0]);

    MatchEvent::factory()->assist()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->yellowCard()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->redCard()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->assists)->toBe(1)
        ->and($player->yellow_cards)->toBe(1)
        ->and($player->red_cards)->toBe(1);
});

test('finalize stats increments matches played for confirmed attendees', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'matches_played' => 0]);

    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->matches_played)->toBe(1);
});

test('finalize stats sets stats_finalized_at timestamp', function () {
    $match = FootballMatch::factory()->completed()->create();

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $match->refresh();
    expect($match->stats_finalized_at)->not->toBeNull();
});

test('revert stats decrements player stats using applied snapshot', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'goals' => 0, 'assists' => 0, 'yellow_cards' => 0, 'matches_played' => 0]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->assist()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(2)
        ->and($player->assists)->toBe(1)
        ->and($player->matches_played)->toBe(1);

    $service->revertStats($match);

    $player->refresh();
    $match->refresh();
    expect($player->goals)->toBe(0)
        ->and($player->assists)->toBe(0)
        ->and($player->matches_played)->toBe(0)
        ->and($match->stats_finalized_at)->toBeNull()
        ->and($match->applied_stats)->toBeNull();
});

test('re-finalize reverts then reapplies stats correctly', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'goals' => 0]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(1);

    // Add another goal and re-finalize
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(2);
});

test('finalize stats updates fouls from events', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'fouls' => 0]);

    MatchEvent::factory()->foul()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->foul()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->foul()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->fouls)->toBe(3);
});

test('revert stats decrements fouls correctly', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'fouls' => 0]);

    MatchEvent::factory()->foul()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->foul()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->fouls)->toBe(2);

    $service->revertStats($match);

    $player->refresh();
    expect($player->fouls)->toBe(0);
});

test('finalize stats updates saves from events', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'saves' => 0]);

    MatchEvent::factory()->save()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->save()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->saves)->toBe(2);
});

test('finalize stats updates handballs from events', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'handballs' => 0]);

    MatchEvent::factory()->handball()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->handballs)->toBe(1);
});

test('finalize stats counts penalty scored as goal and penalties_scored', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'goals' => 0, 'penalties_scored' => 0]);

    MatchEvent::factory()->penaltyScored()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(1)
        ->and($player->penalties_scored)->toBe(1);
});

test('finalize stats counts own goal separately and not as a goal', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'goals' => 0, 'own_goals' => 0]);

    MatchEvent::factory()->ownGoal()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(0)
        ->and($player->own_goals)->toBe(1);
});

test('finalize stats tracks penalty missed', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id, 'penalties_missed' => 0]);

    MatchEvent::factory()->penaltyMissed()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->penalties_missed)->toBe(1);
});

test('revert stats decrements own_goals and penalty stats correctly', function () {
    $match = FootballMatch::factory()->completed()->create();
    $player = Player::factory()->create([
        'club_id' => $match->club_id,
        'goals' => 0, 'own_goals' => 0,
        'penalties_scored' => 0, 'penalties_missed' => 0,
    ]);

    MatchEvent::factory()->ownGoal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->penaltyScored()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->penaltyMissed()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);

    $service = new MatchStatService;
    $service->finalizeStats($match);

    $player->refresh();
    expect($player->goals)->toBe(1)
        ->and($player->own_goals)->toBe(1)
        ->and($player->penalties_scored)->toBe(1)
        ->and($player->penalties_missed)->toBe(1);

    $service->revertStats($match);

    $player->refresh();
    expect($player->goals)->toBe(0)
        ->and($player->own_goals)->toBe(0)
        ->and($player->penalties_scored)->toBe(0)
        ->and($player->penalties_missed)->toBe(0);
});
