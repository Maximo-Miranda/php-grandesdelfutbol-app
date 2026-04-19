<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchEventType;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

const BACKFILL_OP = '2026_04_18_163410_backfill_seasons_and_teams';

beforeEach(function () {
    // Wipe the tracking table so the operation re-runs in each test.
    DB::table('operations')->delete();
});

test('backfill assigns seasons and teams, recalculates scores from events', function () {
    $club = Club::factory()->create();
    $player = Player::factory()->create(['club_id' => $club->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'status' => MatchStatus::Completed,
        'scheduled_at' => now()->subDays(10),
        'team_a_name' => 'Argentina',
        'team_b_name' => 'Japón',
        'team_a_color' => '#2563eb',
        'team_b_color' => '#dc2626',
        'team_a_score' => null,
        'team_b_score' => null,
    ]);
    $match->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);

    MatchEvent::query()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => MatchEventType::Goal,
        'team' => 'a',
        'minute' => 10,
    ]);

    $this->artisan('operations:process', ['name' => BACKFILL_OP, '--sync' => true])->assertSuccessful();

    $match->refresh();
    expect($match->season_id)->not->toBeNull();
    expect($match->team_a_id)->not->toBeNull();
    expect($match->team_b_id)->not->toBeNull();
    expect($match->team_a_score)->toBe(1);
    expect($match->team_b_score)->toBe(0);

    expect(Season::query()->where('club_id', $club->id)->count())->toBe(1);
    expect(Team::query()->where('club_id', $club->id)->count())->toBe(2);
});

test('operation is idempotent when re-run via --test flag', function () {
    $club = Club::factory()->create();

    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $match->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);

    $this->artisan('operations:process', ['name' => BACKFILL_OP, '--sync' => true])->assertSuccessful();
    $teamsAfterFirst = Team::count();
    $seasonsAfterFirst = Season::count();

    $this->artisan('operations:process', ['name' => BACKFILL_OP, '--sync' => true, '--test' => true])
        ->expectsConfirmation('Operation was processed before. Process it again?', 'yes')
        ->assertSuccessful();

    expect(Team::count())->toBe($teamsAfterFirst);
    expect(Season::count())->toBe($seasonsAfterFirst);
});

test('backfill dedupes team names case-insensitively within a season', function () {
    $club = Club::factory()->create();
    $scheduledBase = now()->subDays(30);

    foreach (['Argentina', 'argentina', 'ARGENTINA'] as $i => $name) {
        $match = FootballMatch::factory()->create([
            'club_id' => $club->id,
            'status' => MatchStatus::Completed,
            'scheduled_at' => $scheduledBase->copy()->addDays($i),
            'team_a_name' => $name,
            'team_b_name' => 'Japón',
        ]);
        $match->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);
    }

    $this->artisan('operations:process', ['name' => BACKFILL_OP, '--sync' => true])->assertSuccessful();

    expect(Team::query()->where('club_id', $club->id)->where('normalized_name', 'argentina')->count())->toBe(1);
});

test('backfill takes team name and roster from the LATEST match (no cross-team duplicates)', function () {
    $club = Club::factory()->create();
    $playerA = Player::factory()->create(['club_id' => $club->id]);
    $playerB = Player::factory()->create(['club_id' => $club->id]);

    // First match (older): playerA on team_a "Negros"
    $oldMatch = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->subDays(20),
        'team_a_name' => 'NEGROS',
        'team_a_color' => '#000000',
        'team_b_name' => 'Amarillos',
        'team_b_color' => '#facc15',
    ]);
    $oldMatch->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);
    MatchAttendance::factory()->create([
        'match_id' => $oldMatch->id, 'player_id' => $playerA->id, 'status' => AttendanceStatus::Confirmed, 'team' => AttendanceTeam::A, 'role' => AttendanceRole::Starter,
    ]);

    // Latest match: playerA SWITCHED to team_b "Amarillos" (this is the "current" reality)
    $newMatch = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->subDays(5),
        'team_a_name' => 'Negros',  // tweaked casing — should still dedupe
        'team_a_color' => '#000000',
        'team_b_name' => 'Amarillos',
        'team_b_color' => '#facc15',
    ]);
    $newMatch->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);
    MatchAttendance::factory()->create([
        'match_id' => $newMatch->id, 'player_id' => $playerA->id, 'status' => AttendanceStatus::Confirmed, 'team' => AttendanceTeam::B, 'role' => AttendanceRole::Starter,
    ]);
    MatchAttendance::factory()->create([
        'match_id' => $newMatch->id, 'player_id' => $playerB->id, 'status' => AttendanceStatus::Confirmed, 'team' => AttendanceTeam::A, 'role' => AttendanceRole::Starter,
    ]);

    $this->artisan('operations:process', ['name' => BACKFILL_OP, '--sync' => true])->assertSuccessful();

    $negros = Team::query()->where('club_id', $club->id)->where('normalized_name', 'negros')->first();
    $amarillos = Team::query()->where('club_id', $club->id)->where('normalized_name', 'amarillos')->first();

    expect($negros)->not->toBeNull();
    expect($amarillos)->not->toBeNull();

    // Latest match's name wins (lowercase "Negros", not first-occurrence "NEGROS")
    expect($negros->name)->toBe('Negros');

    // playerA only on Amarillos (their LAST team), not on Negros
    expect($amarillos->players->pluck('id')->all())->toContain($playerA->id);
    expect($negros->players->pluck('id')->all())->not->toContain($playerA->id);

    // playerB on Negros (only team they played for in the latest match)
    expect($negros->players->pluck('id')->all())->toContain($playerB->id);
});

test('does not re-run automatically when already processed', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $match->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);

    $this->artisan('operations:process', ['name' => BACKFILL_OP, '--sync' => true])->assertSuccessful();
    expect(DB::table('operations')->where('name', BACKFILL_OP)->count())->toBe(1);

    // A second generic operations:process call should NOT reprocess this one.
    $match2 = FootballMatch::factory()->create(['club_id' => $club->id]);
    $match2->updateQuietly(['season_id' => null, 'team_a_id' => null, 'team_b_id' => null]);

    $this->artisan('operations:process', ['--sync' => true])->assertSuccessful();
    expect($match2->fresh()->season_id)->toBeNull();
});
