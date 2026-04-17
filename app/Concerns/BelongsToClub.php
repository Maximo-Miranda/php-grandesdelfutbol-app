<?php

namespace App\Concerns;

use App\Models\Scopes\ClubScope;

trait BelongsToClub
{
    public static function bootBelongsToClub(): void
    {
        static::addGlobalScope(new ClubScope);
    }

    public function scopeAnyClub($query)
    {
        return $query->withoutGlobalScope(ClubScope::class);
    }
}
