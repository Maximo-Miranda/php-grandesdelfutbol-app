<?php

use Illuminate\Support\Facades\DB;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        DB::table('match_video_uploads')
            ->where('status', 'encoding')
            ->whereNotNull('best_resolution')
            ->update([
                'status' => 'ready',
                'error_message' => null,
                'updated_at' => now(),
            ]);
    }
};
