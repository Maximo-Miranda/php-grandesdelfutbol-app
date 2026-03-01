<?php

namespace App\Policies;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }

    public function view(User $user, Venue $venue): bool
    {
        return $venue->club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }

    public function create(User $user, Club $club): bool
    {
        return $club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Owner])
            ->exists();
    }

    public function update(User $user, Venue $venue): bool
    {
        return $venue->club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Owner])
            ->exists();
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $this->update($user, $venue);
    }
}
