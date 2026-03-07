<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\User;

class FootballMatchPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->isApprovedMember($user);
    }

    public function view(User $user, FootballMatch $match): bool
    {
        return $match->club->isApprovedMember($user);
    }

    public function create(User $user, Club $club): bool
    {
        return $club->isAdminOrOwner($user);
    }

    public function update(User $user, FootballMatch $match): bool
    {
        return $match->club->isAdminOrOwner($user);
    }

    public function delete(User $user, FootballMatch $match): bool
    {
        return $this->update($user, $match);
    }

    public function register(User $user, FootballMatch $match): bool
    {
        return $match->club->isApprovedMember($user);
    }
}
