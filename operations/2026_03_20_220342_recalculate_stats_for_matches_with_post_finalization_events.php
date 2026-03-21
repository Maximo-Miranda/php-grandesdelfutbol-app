<?php

use App\Models\FootballMatch;
use App\Services\MatchStatService;
use Illuminate\Support\Facades\Log;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Re-finalize stats for completed matches that have events created after stats_finalized_at.
 *
 * Why: The MatchEventController did not auto-refresh stats when events were added
 * after finalization. This caused player stats to be out of sync with match events.
 */
return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $statService = app(MatchStatService::class);

        $matches = FootballMatch::query()
            ->whereNotNull('stats_finalized_at')
            ->whereHas('events', function ($query) {
                $query->whereColumn('created_at', '>', 'matches.stats_finalized_at');
            })
            ->get();

        foreach ($matches as $match) {
            $statService->finalizeStats($match);

            Log::info("[OneTimeOperation] Re-finalized stats for match #{$match->id} ({$match->title}).");
        }

        Log::info("[OneTimeOperation] Re-finalized stats for {$matches->count()} match(es) with post-finalization events.");
    }
};
