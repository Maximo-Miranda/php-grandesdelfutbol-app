<?php

use App\Enums\MatchEventType;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Services\MatchStatService;

test('marking a completed match as friendly reverts applied player stats', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->forSeason($season)->create();
    $teamB = Team::factory()->forSeason($season)->create();

    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'team_a_score' => 2,
        'team_b_score' => 1,
        'is_friendly' => false,
    ]);

    $scorer = Player::factory()->create(['club_id' => $club->id, 'goals' => 0, 'matches_played' => 0]);

    MatchEvent::query()->create([
        'match_id' => $match->id,
        'player_id' => $scorer->id,
        'event_type' => MatchEventType::Goal,
        'minute' => 10,
    ]);

    app(MatchStatService::class)->finalizeStats($match);

    expect($scorer->fresh()->goals)->toBe(1);

    $match->update(['is_friendly' => true]);

    expect($scorer->fresh()->goals)->toBe(0);
});
