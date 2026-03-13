<?php

use App\Models\FootballMatch;
use Illuminate\Support\Facades\Log;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Recalculate attendance roles (starter/substitute) per team.
 *
 * Why: The old logic determined starter vs substitute using the global confirmed
 * count across both teams. This caused players to be marked as substitutes even
 * when their team still had starter slots available. The fix counts per-team,
 * using max_players / 2 as the starter cap per team.
 */
return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $service = app(\App\Services\MatchService::class);

        $matches = FootballMatch::query()
            ->whereHas('attendances', fn ($q) => $q->where('status', 'confirmed'))
            ->get();

        foreach ($matches as $match) {
            $service->recalculateRoles($match);
        }

        Log::info("[OneTimeOperation] Recalculated attendance roles for {$matches->count()} matches.");
    }
};
