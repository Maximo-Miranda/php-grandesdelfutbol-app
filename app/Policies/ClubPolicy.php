<?php

namespace App\Policies;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Club $club): bool
    {
        return $club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Club $club): bool
    {
        return $club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Owner])
            ->exists();
    }

    public function delete(User $user, Club $club): bool
    {
        return $club->owner_id === $user->id;
    }
}
