<?php

namespace App\Policies;

use App\Models\ClubMember;
use App\Models\User;

class ClubMemberPolicy
{
    public function updateRole(User $user, ClubMember $target): bool
    {
        $actor = $target->club->getMembership($user);

        if (! $actor) {
            return false;
        }

        return $actor->role->outranks($target->role);
    }

    public function remove(User $user, ClubMember $target): bool
    {
        if ($target->user_id === $user->id) {
            return false;
        }

        $actor = $target->club->getMembership($user);

        if (! $actor) {
            return false;
        }

        return $actor->role->outranks($target->role);
    }

    public function leave(User $user, ClubMember $target): bool
    {
        if ($target->user_id !== $user->id) {
            return false;
        }

        return ! $target->isOwner();
    }
}
