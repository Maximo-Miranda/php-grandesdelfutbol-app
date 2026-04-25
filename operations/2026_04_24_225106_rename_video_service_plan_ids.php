<?php

use Illuminate\Support\Facades\DB;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        DB::table('video_service_requests')
            ->whereIn('selected_plan', ['recocha', 'profesional'])
            ->update(['selected_plan' => 'partido_pro']);
    }
};
