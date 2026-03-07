<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->isApprovedMember($user);
    }

    public function view(User $user, Player $player): bool
    {
        return $player->club->isApprovedMember($user);
    }

    public function create(User $user, Club $club): bool
    {
        return $club->isAdminOrOwner($user);
    }

    public function update(User $user, Player $player): bool
    {
        return $player->club->isAdminOrOwner($user)
            || ($player->user_id !== null && $player->user_id === $user->id);
    }

    public function delete(User $user, Player $player): bool
    {
        return $this->update($user, $player);
    }
}
