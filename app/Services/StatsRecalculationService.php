<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatsRecalculationService
{
    public function __construct(
        private readonly MatchStatService $matchStats,
        private readonly SeasonService $seasons,
    ) {}

    /**
     * Re-apply/revert player stats and reconcile season state after match changes.
     *
     * @param  array<string, mixed>  $original  The match attributes before save.
     */
    public function handle(FootballMatch $match, array $original): void
    {
        $isFriendly = (bool) $match->is_friendly;
        $wasFriendly = (bool) ($original['is_friendly'] ?? false);

        $status = $match->status instanceof MatchStatus ? $match->status : MatchStatus::from((string) $match->status);
        $originalStatus = isset($original['status'])
            ? ($original['status'] instanceof MatchStatus ? $original['status'] : MatchStatus::tryFrom((string) $original['status']))
            : null;

        $statsRelevantChanged = $isFriendly !== $wasFriendly
            || ($status !== $originalStatus)
            || (($original['team_a_score'] ?? null) !== $match->team_a_score)
            || (($original['team_b_score'] ?? null) !== $match->team_b_score)
            || (($original['team_a_id'] ?? null) !== $match->team_a_id)
            || (($original['team_b_id'] ?? null) !== $match->team_b_id);

        if (! $statsRelevantChanged) {
            return;
        }

        DB::transaction(function () use ($match, $isFriendly, $wasFriendly, $status) {
            $hadStats = ! empty($match->applied_stats) || $match->stats_finalized_at !== null;

            if ($isFriendly && $hadStats) {
                $this->matchStats->revertStats($match->fresh());
                Log::info('Reverted stats for match (became friendly)', ['match_id' => $match->id]);
            }

            if (! $isFriendly && $wasFriendly && $status === MatchStatus::Completed && $match->events()->exists()) {
                $this->matchStats->finalizeStats($match->fresh());
                Log::info('Re-applied stats for match (no longer friendly)', ['match_id' => $match->id]);
            }

            $seasonId = $match->season_id ?? ($original['season_id'] ?? null);
            if ($seasonId) {
                $season = Season::query()->withoutGlobalScopes()->find($seasonId);
                if ($season) {
                    $this->seasons->reopenIfIncomplete($season);
                    $this->seasons->finalizeIfComplete($season);
                }
            }
        });
    }
}
