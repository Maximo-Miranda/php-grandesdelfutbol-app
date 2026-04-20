<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\StandingsService;

test('team standings recompute after adding a goal event to a finalized match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->forSeason($season)->create(['name' => 'Alfa']);
    $teamB = Team::factory()->forSeason($season)->create(['name' => 'Beta']);

    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'team_a_score' => 1,
        'team_b_score' => 1,
        'is_friendly' => false,
    ]);

    $playerA = Player::factory()->create(['club_id' => $club->id]);
    $playerB = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id, 'player_id' => $playerA->id, 'status' => 'confirmed', 'team' => 'a',
    ]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id, 'player_id' => $playerB->id, 'status' => 'confirmed', 'team' => 'b',
    ]);

    // Pre-existing goals matching the score (1-1)
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $playerA->id, 'team' => 'a']);
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $playerB->id, 'team' => 'b']);

    // Pre-finalize so stats_finalized_at is set
    $this->actingAs($user)->post(route('clubs.matches.finalizeStats', [$club, $match]));

    $standings = app(StandingsService::class);
    $before = $standings->forSeason($season->fresh());
    $alfaBefore = $before->firstWhere('name', 'Alfa');
    expect($alfaBefore['Pts'])->toBe(1);
    expect($alfaBefore['G'])->toBe(0);
    expect($alfaBefore['E'])->toBe(1);

    // Add a goal event for team A — should make Alfa win 2-1
    $this->actingAs($user)->post(route('clubs.matches.events.store', [$club, $match]), [
        'player_id' => $playerA->id,
        'event_type' => 'goal',
        'team' => 'a',
        'minute' => 50,
    ])->assertRedirect();

    $match->refresh();
    expect($match->team_a_score)->toBe(2);

    $after = $standings->forSeason($season->fresh());
    $alfaAfter = $after->firstWhere('name', 'Alfa');
    expect($alfaAfter['Pts'])->toBe(3);
    expect($alfaAfter['G'])->toBe(1);
    expect($alfaAfter['E'])->toBe(0);
    expect($alfaAfter['GF'])->toBe(2);

    $playerA->refresh();
    expect($playerA->goals)->toBe(2); // pre-existing goal + the new one we just added
});

test('team standings reflect score change after deleting a goal event', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->forSeason($season)->create(['name' => 'Alfa']);
    $teamB = Team::factory()->forSeason($season)->create(['name' => 'Beta']);

    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'team_a_score' => 2,
        'team_b_score' => 0,
        'is_friendly' => false,
    ]);

    $player = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $player->id, 'status' => 'confirmed', 'team' => 'a']);

    // Two goal events to match the 2-0 score
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id, 'team' => 'a']);
    $event2 = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id, 'team' => 'a']);

    $this->actingAs($user)->post(route('clubs.matches.finalizeStats', [$club, $match]));

    // Delete one goal — score should drop to 1-0, standings still W
    $this->actingAs($user)->delete(route('clubs.matches.events.destroy', [$club, $match, $event2]))->assertRedirect();

    $match->refresh();
    expect($match->team_a_score)->toBe(1);

    $standings = app(StandingsService::class);
    $alfa = $standings->forSeason($season->fresh())->firstWhere('name', 'Alfa');
    expect($alfa['Pts'])->toBe(3); // still a win
    expect($alfa['GF'])->toBe(1);

    $player->refresh();
    expect($player->goals)->toBe(1);
});
