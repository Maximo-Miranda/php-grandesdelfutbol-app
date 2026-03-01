<?php

namespace App\Policies;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\User;

class FootballMatchPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }

    public function view(User $user, FootballMatch $match): bool
    {
        return $match->club->members()
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

    public function update(User $user, FootballMatch $match): bool
    {
        return $match->club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Owner])
            ->exists();
    }

    public function delete(User $user, FootballMatch $match): bool
    {
        return $this->update($user, $match);
    }

    public function register(User $user, FootballMatch $match): bool
    {
        return $match->club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }
}
