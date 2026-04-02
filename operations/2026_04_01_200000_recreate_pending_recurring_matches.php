<?php

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Services\MatchService;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    /**
     * Recreate next match for completed recurring matches that were
     * completed before the recurrence feature was deployed.
     */
    public function process(): void
    {
        $matchService = app(MatchService::class);

        FootballMatch::query()
            ->where('status', MatchStatus::Completed)
            ->where('is_recurring', true)
            ->whereNull('next_match_created_at')
            ->each(fn (FootballMatch $match) => $matchService->recreateIfRecurring($match));
    }
};
