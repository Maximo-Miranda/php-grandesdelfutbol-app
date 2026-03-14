<?php

use App\Enums\AttendanceStatus;
use App\Enums\MatchStatus;
use App\Models\MatchAttendance;
use App\Models\Player;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    /**
     * Recalculate matches_played for all players from actual attendance data.
     * Fixes inflated counts caused by the merge summing matches_played.
     */
    public function process(): void
    {
        $correctCounts = MatchAttendance::query()
            ->where('status', AttendanceStatus::Confirmed)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->selectRaw('player_id, count(*) as total')
            ->groupBy('player_id')
            ->pluck('total', 'player_id');

        Player::query()->each(function (Player $player) use ($correctCounts) {
            $correct = $correctCounts[$player->id] ?? 0;

            if ($player->matches_played !== $correct) {
                $player->update(['matches_played' => $correct]);
            }
        });
    }
};
