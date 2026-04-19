<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->isApprovedMember($user);
    }

    public function view(User $user, Season $season): bool
    {
        return $season->club->isApprovedMember($user);
    }

    public function update(User $user, Season $season): bool
    {
        return $season->club->isAdminOrOwner($user) && $season->isActive();
    }
}
