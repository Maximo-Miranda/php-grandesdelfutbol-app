<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->isApprovedMember($user);
    }

    public function view(User $user, Team $team): bool
    {
        return $team->club->isApprovedMember($user);
    }

    public function create(User $user, Club $club): bool
    {
        return $club->isAdminOrOwner($user);
    }

    public function update(User $user, Team $team): bool
    {
        return $team->club->isAdminOrOwner($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $team->club->isAdminOrOwner($user);
    }
}
