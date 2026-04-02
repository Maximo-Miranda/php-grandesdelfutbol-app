<?php

use Illuminate\Support\Facades\DB;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        DB::table('clubs')
            ->whereNotNull('google_drive_folder_id')
            ->update([
                'google_drive_folder_id' => null,
                'updated_at' => now(),
            ]);
    }
};
