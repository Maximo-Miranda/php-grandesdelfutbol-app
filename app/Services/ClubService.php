<?php

namespace App\Services;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Str;

class ClubService
{
    public function createClub(User $user, array $data): Club
    {
        $club = Club::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'owner_id' => $user->id,
            'invite_token' => Str::random(32),
            'requires_approval' => $data['requires_approval'] ?? false,
        ]);

        ClubMember::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => ClubMemberRole::Owner,
            'status' => ClubMemberStatus::Approved,
            'approved_at' => now(),
        ]);

        Player::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'is_active' => true,
        ]);

        $this->switchToClub($user, $club);

        return $club;
    }

    public function switchToClub(User $user, Club $club): void
    {
        $user->update(['last_club_id' => $club->id]);
        session()->put('active_club_id', $club->id);
    }

    /**
     * Find a valid club for the user: checks given club ID first, then falls back to any club.
     * Returns null if user has no clubs.
     */
    public function resolveForUser(User $user, ?int $clubId = null): ?Club
    {
        if ($clubId) {
            $club = Club::query()->forUser($user)->where('clubs.id', $clubId)->first();

            if ($club) {
                return $club;
            }
        }

        return Club::query()->forUser($user)
            ->join('club_members', 'clubs.id', '=', 'club_members.club_id')
            ->where('club_members.user_id', $user->id)
            ->orderByDesc('club_members.created_at')
            ->select('clubs.*')
            ->first();
    }
}
