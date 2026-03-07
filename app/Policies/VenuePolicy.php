<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->isApprovedMember($user);
    }

    public function view(User $user, Venue $venue): bool
    {
        return $venue->club->isApprovedMember($user);
    }

    public function create(User $user, Club $club): bool
    {
        return $club->isAdminOrOwner($user);
    }

    public function update(User $user, Venue $venue): bool
    {
        return $venue->club->isAdminOrOwner($user);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $this->update($user, $venue);
    }
}
