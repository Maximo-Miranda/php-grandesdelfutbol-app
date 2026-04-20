<?php

namespace App\Observers;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\MatchAttendance;

class MatchAttendanceObserver
{
    public function saved(MatchAttendance $attendance): void
    {
        if ($attendance->status !== AttendanceStatus::Confirmed) {
            return;
        }

        if (! $attendance->team instanceof AttendanceTeam) {
            return;
        }

        $match = $attendance->match;
        if (! $match) {
            return;
        }

        $teamId = $attendance->team === AttendanceTeam::A ? $match->team_a_id : $match->team_b_id;
        if (! $teamId) {
            return;
        }

        $match->loadMissing([
            'teamA.players' => fn ($q) => $q->withoutGlobalScopes(),
            'teamB.players' => fn ($q) => $q->withoutGlobalScopes(),
        ]);

        $team = $attendance->team === AttendanceTeam::A ? $match->teamA : $match->teamB;
        if (! $team) {
            return;
        }

        $team->attachPlayerExclusively($attendance->player_id);
    }
}
