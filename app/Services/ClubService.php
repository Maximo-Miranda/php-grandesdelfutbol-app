<?php

namespace App\Services;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
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

        $club->members()->create([
            'user_id' => $user->id,
            'role' => ClubMemberRole::Owner,
            'status' => ClubMemberStatus::Approved,
            'approved_at' => now(),
        ]);

        return $club;
    }
}
