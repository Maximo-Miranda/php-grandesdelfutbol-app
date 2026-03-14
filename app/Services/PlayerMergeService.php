<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\MatchStatus;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class PlayerMergeService
{
    private const STAT_COLUMNS = [
        'goals', 'assists', 'yellow_cards',
        'red_cards', 'fouls', 'saves', 'handballs',
        'own_goals', 'penalties_scored', 'penalties_missed',
    ];

    /**
     * Merge source player into target player: sum stats, transfer events/attendances, delete source.
     */
    public function merge(Player $source, Player $target): Player
    {
        return DB::transaction(function () use ($source, $target) {
            $this->sumStats($source, $target);
            $this->transferMatchEvents($source, $target);
            $this->transferMatchAttendances($source, $target);
            $this->recalculateMatchesPlayed($target);
            $this->adoptProfileAttributes($source, $target);

            $target->save();
            $source->delete();

            return $target->fresh();
        });
    }

    private function sumStats(Player $source, Player $target): void
    {
        foreach (self::STAT_COLUMNS as $column) {
            $target->{$column} += $source->{$column};
        }
    }

    private function recalculateMatchesPlayed(Player $target): void
    {
        $target->matches_played = MatchAttendance::where('player_id', $target->id)
            ->where('status', AttendanceStatus::Confirmed)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->count();
    }

    private function transferMatchEvents(Player $source, Player $target): void
    {
        MatchEvent::where('player_id', $source->id)
            ->update(['player_id' => $target->id]);
    }

    private function transferMatchAttendances(Player $source, Player $target): void
    {
        // Find matches where both players have attendance (conflict)
        $conflictMatchIds = MatchAttendance::where('player_id', $source->id)
            ->whereIn('match_id', function ($query) use ($target) {
                $query->select('match_id')
                    ->from('match_attendances')
                    ->where('player_id', $target->id);
            })
            ->pluck('match_id');

        // Delete source attendances that conflict (target's attendance already exists)
        MatchAttendance::where('player_id', $source->id)
            ->whereIn('match_id', $conflictMatchIds)
            ->delete();

        // Transfer remaining source attendances to target
        MatchAttendance::where('player_id', $source->id)
            ->update(['player_id' => $target->id]);
    }

    private function adoptProfileAttributes(Player $source, Player $target): void
    {
        $target->position ??= $source->position;
        $target->jersey_number ??= $source->jersey_number;
    }
}
