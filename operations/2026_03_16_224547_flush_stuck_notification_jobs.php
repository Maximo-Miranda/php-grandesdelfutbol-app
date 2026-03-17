<?php

use App\Models\FootballMatch;
use Illuminate\Support\Facades\DB;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        DB::table('jobs')->where('queue', 'notifications')->delete();
        DB::table('failed_jobs')->where('queue', 'notifications')->delete();

        FootballMatch::query()
            ->where('status', 'upcoming')
            ->whereNotNull('registration_notified_at')
            ->update(['registration_notified_at' => null]);
    }
};
