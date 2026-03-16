<?php

use App\Models\Club;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        Club::query()->whereNull('slug')->each(function (Club $club) {
            $club->update(['slug' => Club::generateUniqueSlug($club->name)]);
        });
    }
};
