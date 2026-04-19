<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Models\Team;
use App\Services\MatchStatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Backfill seasons and teams for matches that predate the Posiciones feature.
 *
 * For each club with legacy matches (season_id IS NULL):
 *  - Recalculates score from events where manual scores are missing.
 *  - Groups matches chronologically into seasons of Season::DEFAULT_MATCHES_COUNT
 *    (non-cancelled, non-friendly matches count toward the cap).
 *  - Dedupes teams case-insensitively within each season.
 *  - For each team: name, color, and roster come from the LATEST match where the
 *    team participated (avoids cross-match player-on-both-teams duplicates and
 *    keeps the most current naming/color).
 *  - Reuses pre-existing empty seasons (from users who browsed /standings before
 *    this operation ran).
 *
 * Idempotent: re-running does nothing once matches have season_id set.
 */
return new class extends OneTimeOperation
{
    protected bool $async = false;

    protected ?string $tag = null;

    public function process(): void
    {
        $matchStats = app(MatchStatService::class);

        $totals = [
            'seasons' => 0,
            'teams' => 0,
            'matches' => 0,
            'scores_recalculated' => 0,
            'rosters' => 0,
        ];

        foreach (Club::query()->get() as $club) {
            DB::transaction(function () use ($club, $matchStats, &$totals) {
                $result = $this->processClub($club, $matchStats);
                foreach ($totals as $key => $_) {
                    $totals[$key] += $result[$key] ?? 0;
                }
                if (($result['matches'] ?? 0) > 0) {
                    Log::info('[OneTimeOperation] Backfilled club', [
                        'club_id' => $club->id,
                        ...$result,
                    ]);
                }
            });
        }

        Log::info('[OneTimeOperation] Backfill complete', $totals);
    }

    /**
     * @return array{seasons: int, teams: int, matches: int, scores_recalculated: int, rosters: int}
     */
    private function processClub(Club $club, MatchStatService $matchStats): array
    {
        $stats = ['seasons' => 0, 'teams' => 0, 'matches' => 0, 'scores_recalculated' => 0, 'rosters' => 0];

        $matchesToProcess = FootballMatch::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->whereNull('season_id')
            ->orderBy('scheduled_at')
            ->get();

        if ($matchesToProcess->isEmpty()) {
            return $stats;
        }

        foreach ($matchesToProcess as $match) {
            if ($match->status !== MatchStatus::Completed) {
                continue;
            }
            if ($match->team_a_score !== null && $match->team_b_score !== null) {
                continue;
            }
            if (! $match->events()->exists()) {
                continue;
            }
            $matchStats->recalculateScore($match);
            $match->refresh();
            $stats['scores_recalculated']++;
        }

        $seasonSize = Season::DEFAULT_MATCHES_COUNT;

        $orphanSeasons = Season::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->doesntHave('matches')
            ->orderBy('created_at')
            ->get();

        $existingSeasonCount = Season::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->has('matches')
            ->count();

        $matchToSeason = [];
        $currentSeason = null;
        $currentCount = 0;
        $seasonIndex = $existingSeasonCount + 1;

        foreach ($matchesToProcess as $match) {
            $countsTowardSeason = $match->status !== MatchStatus::Cancelled && ! $match->is_friendly;

            if ($currentSeason === null || ($countsTowardSeason && $currentCount >= $seasonSize)) {
                if ($currentSeason !== null) {
                    $currentSeason->update([
                        'status' => SeasonStatus::Completed,
                        'completed_at' => $currentSeason->completed_at ?? now(),
                    ]);
                }

                $reused = $orphanSeasons->shift();
                if ($reused) {
                    $reused->update([
                        'name' => 'Temporada #'.$seasonIndex,
                        'matches_count' => $seasonSize,
                        'status' => SeasonStatus::Active,
                        'completed_at' => null,
                    ]);
                    $currentSeason = $reused;
                } else {
                    $currentSeason = Season::query()->create([
                        'club_id' => $club->id,
                        'name' => 'Temporada #'.$seasonIndex,
                        'matches_count' => $seasonSize,
                        'status' => SeasonStatus::Active,
                    ]);
                    $stats['seasons']++;
                }

                $seasonIndex++;
                $currentCount = 0;
            }

            $matchToSeason[$match->id] = $currentSeason;

            if ($countsTowardSeason) {
                $currentCount++;
            }
        }

        if ($currentSeason !== null && $currentCount >= $seasonSize) {
            $currentSeason->update([
                'status' => SeasonStatus::Completed,
                'completed_at' => now(),
            ]);
        }

        foreach ($orphanSeasons as $remaining) {
            $remaining->delete();
        }

        $teamCache = [];
        foreach ($matchesToProcess as $match) {
            $season = $matchToSeason[$match->id] ?? null;
            if (! $season) {
                continue;
            }

            $teamA = $this->getOrCreateTeam($teamCache, $club, $season, $match->team_a_name, $match->team_a_color, $stats);
            $teamB = null;
            if (! empty($match->team_b_name)) {
                $teamB = $this->getOrCreateTeam($teamCache, $club, $season, $match->team_b_name, $match->team_b_color, $stats);
            }

            $updates = ['season_id' => $season->id];
            if ($teamA) {
                $updates['team_a_id'] = $teamA->id;
            }
            if ($teamB) {
                $updates['team_b_id'] = $teamB->id;
            }
            $match->updateQuietly($updates);
            $stats['matches']++;
        }

        // For each team: take name + color + roster from the LATEST match it played in.
        // This avoids the duplication issue where players confirmed for both teams across
        // different matches in the same season — only the most recent match's roster wins.
        foreach ($teamCache as $team) {
            $latestMatch = FootballMatch::query()->withoutGlobalScopes()
                ->where('season_id', $team->season_id)
                ->where(fn ($q) => $q->where('team_a_id', $team->id)->orWhere('team_b_id', $team->id))
                ->orderByDesc('scheduled_at')
                ->orderByDesc('id')
                ->first();

            if (! $latestMatch) {
                continue;
            }

            // Update name/color to the latest match's version (keeps the most current naming)
            $isA = $latestMatch->team_a_id === $team->id;
            $latestName = $isA ? $latestMatch->team_a_name : $latestMatch->team_b_name;
            $latestColor = $isA ? $latestMatch->team_a_color : $latestMatch->team_b_color;
            if ($latestName && ($latestName !== $team->name || $latestColor !== $team->color)) {
                $team->update([
                    'name' => $latestName,
                    'color' => $latestColor ?? $team->color,
                ]);
            }

            // Roster = confirmed attendees of the latest match assigned to this team's slot
            $teamSlot = $isA ? AttendanceTeam::A : AttendanceTeam::B;
            $playerIds = $latestMatch->attendances()
                ->where('status', AttendanceStatus::Confirmed)
                ->where('team', $teamSlot)
                ->pluck('player_id')
                ->unique()
                ->all();

            if (! empty($playerIds)) {
                // Remove any prior assignments to sibling teams in the same season first
                $team->detachPlayersFromSiblings($playerIds);
                $team->players()->sync($playerIds);
                $stats['rosters'] += count($playerIds);
            } else {
                // No attendees on the latest match — leave roster empty (admin can fill later)
                $team->players()->sync([]);
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, Team>  $cache
     * @param  array<string, int>  $stats
     */
    private function getOrCreateTeam(array &$cache, Club $club, Season $season, ?string $name, ?string $color, array &$stats): ?Team
    {
        if (empty($name)) {
            return null;
        }

        $normalized = Team::normalize($name);
        $cacheKey = $season->id.'|'.$normalized;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $existing = Team::query()->withoutGlobalScopes()
            ->where('season_id', $season->id)
            ->where('normalized_name', $normalized)
            ->first();

        if ($existing) {
            $cache[$cacheKey] = $existing;

            return $existing;
        }

        $team = Team::query()->create([
            'club_id' => $club->id,
            'season_id' => $season->id,
            'name' => $name,
            'color' => $color ?? '#1a1a1a',
        ]);

        $cache[$cacheKey] = $team;
        $stats['teams']++;

        return $team;
    }
};
