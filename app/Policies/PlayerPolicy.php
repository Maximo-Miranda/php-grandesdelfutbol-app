<?php

namespace App\Policies;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }

    public function view(User $user, Player $player): bool
    {
        return $player->club->members()
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

    public function update(User $user, Player $player): bool
    {
        return $player->club->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Owner])
            ->exists();
    }

    public function delete(User $user, Player $player): bool
    {
        return $this->update($user, $player);
    }
}
