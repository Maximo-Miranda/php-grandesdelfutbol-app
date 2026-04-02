<?php

use App\Models\FootballMatch;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    /**
     * Disable recurrence and auto-cancel on all existing matches so the
     * features only apply to matches created after this deployment.
     */
    public function process(): void
    {
        FootballMatch::query()->update([
            'is_recurring' => false,
            'auto_cancel' => false,
        ]);
    }
};
