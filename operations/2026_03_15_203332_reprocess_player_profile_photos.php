<?php

use Illuminate\Support\Facades\Artisan;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        Artisan::call('media-library:regenerate', [
            'modelType' => 'App\Models\PlayerProfile',
            '--force' => true,
        ]);
    }
};
