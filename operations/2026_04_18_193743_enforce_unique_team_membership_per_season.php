<?php

use App\Enums\AttendanceStatus;
use App\Models\MatchAttendance;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Cleanup operation: a player should only belong to ONE team per season.
 * The backfill produced cases where a player was rostered in multiple teams
 * (e.g. they confirmed attendance for different teams across matches).
 *
 * Resolution rule: keep them in the team where they LAST confirmed attendance.
 * If they have no confirmed attendances on any of the conflicting teams' matches,
 * keep the most recent team_player row (latest created_at).
 *
 * Idempotent — safe to re-run.
 */
return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $duplicates = DB::select(
            'SELECT tp.player_id, t.season_id, COUNT(*) AS team_count
             FROM team_player tp
             JOIN teams t ON t.id = tp.team_id
             GROUP BY tp.player_id, t.season_id
             HAVING COUNT(*) > 1'
        );

        $cleaned = 0;
        $reassigned = 0;

        foreach ($duplicates as $dup) {
            $teamIds = DB::table('team_player as tp')
                ->join('teams as t', 't.id', '=', 'tp.team_id')
                ->where('tp.player_id', $dup->player_id)
                ->where('t.season_id', $dup->season_id)
                ->pluck('t.id')
                ->all();

            $keepTeamId = $this->resolveKeeper((int) $dup->player_id, $teamIds);

            $detachIds = array_filter($teamIds, fn ($id) => $id !== $keepTeamId);

            DB::table('team_player')
                ->where('player_id', $dup->player_id)
                ->whereIn('team_id', $detachIds)
                ->delete();

            $cleaned += count($detachIds);
            $reassigned++;
        }

        Log::info('[OneTimeOperation] Unique team membership enforced', [
            'players_processed' => $reassigned,
            'duplicate_rows_removed' => $cleaned,
        ]);
    }

    /**
     * Pick the team to keep for a player among conflicting teams.
     * Priority: most recent confirmed attendance → most recent team_player insertion.
     *
     * @param  array<int>  $teamIds
     */
    private function resolveKeeper(int $playerId, array $teamIds): int
    {
        $lastConfirmed = MatchAttendance::query()
            ->where('player_id', $playerId)
            ->where('status', AttendanceStatus::Confirmed)
            ->whereNotNull('team')
            ->whereHas('match', function ($q) use ($teamIds) {
                $q->withoutGlobalScopes()
                    ->where(function ($inner) use ($teamIds) {
                        $inner->whereIn('team_a_id', $teamIds)->orWhereIn('team_b_id', $teamIds);
                    });
            })
            ->with(['match' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByDesc('confirmed_at')
            ->first();

        if ($lastConfirmed && $lastConfirmed->match) {
            $resolved = $lastConfirmed->team->value === 'a'
                ? $lastConfirmed->match->team_a_id
                : $lastConfirmed->match->team_b_id;
            if ($resolved && in_array($resolved, $teamIds, true)) {
                return $resolved;
            }
        }

        // Fallback: keep the most recently inserted team_player row
        $latest = DB::table('team_player')
            ->where('player_id', $playerId)
            ->whereIn('team_id', $teamIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('team_id');

        return (int) ($latest ?? $teamIds[0]);
    }
};
