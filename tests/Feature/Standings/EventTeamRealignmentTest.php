<?php

use App\Enums\AttendanceTeam;
use App\Enums\MatchEventType;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\MatchService;

test('assigning teams to a free-text match re-credits events to the player roster team', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $argentina = Team::factory()->forSeason($season)->create(['name' => 'Argentina']);
    $negro = Team::factory()->forSeason($season)->create(['name' => 'Negro']);

    // Camilo belongs to Negro's roster
    $camilo = Player::factory()->create(['club_id' => $club->id]);
    $negro->players()->attach($camilo->id);

    // Match created without team entities — Camilo's goal labeled as 'a' (arbitrary)
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => null,
        'team_b_id' => null,
        'team_a_name' => 'Equipo libre A',
        'team_b_name' => 'Equipo libre B',
        'team_a_score' => 1,
        'team_b_score' => 0,
    ]);
    MatchEvent::query()->create([
        'match_id' => $match->id,
        'player_id' => $camilo->id,
        'event_type' => MatchEventType::Goal,
        'team' => AttendanceTeam::A,
        'minute' => 10,
    ]);

    // Admin updates the match assigning real teams (team_a = Argentina, team_b = Negro)
    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toIso8601String(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => $match->max_players,
            'max_substitutes' => $match->max_substitutes,
            'registration_opens_hours' => $match->registration_opens_hours,
            'team_a_id' => $argentina->id,
            'team_b_id' => $negro->id,
        ])
        ->assertRedirect();

    // Camilo's goal must now be credited to team B (Negro), not team A (Argentina)
    $event = MatchEvent::query()->where('match_id', $match->id)->where('player_id', $camilo->id)->first();
    expect($event->team)->toBe(AttendanceTeam::B);

    // Score must be recalculated: team A (Argentina) = 0, team B (Negro) = 1
    $match->refresh();
    expect($match->team_a_score)->toBe(0);
    expect($match->team_b_score)->toBe(1);
});

test('realignment is idempotent — running again with already-correct events does nothing', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->forSeason($season)->create();
    $teamB = Team::factory()->forSeason($season)->create();

    $playerA = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($playerA->id);

    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id, 'season_id' => $season->id,
        'team_a_id' => $teamA->id, 'team_b_id' => $teamB->id,
    ]);
    MatchEvent::query()->create([
        'match_id' => $match->id, 'player_id' => $playerA->id,
        'event_type' => MatchEventType::Goal, 'team' => AttendanceTeam::A, 'minute' => 5,
    ]);

    $service = app(MatchService::class);
    $changed = $service->realignEventTeamsToRosters($match->fresh());

    expect($changed)->toBe(0);
});
