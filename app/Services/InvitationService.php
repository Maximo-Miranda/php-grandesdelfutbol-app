<?php

namespace App\Services;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\InvitationStatus;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\User;
use App\Notifications\ClubInvitationNotification;
use Illuminate\Support\Str;

class InvitationService
{
    public function sendInvitation(Club $club, string $email, User $inviter): ClubInvitation
    {
        $invitation = ClubInvitation::query()->create([
            'club_id' => $club->id,
            'email' => $email,
            'token' => Str::random(32),
            'status' => InvitationStatus::Pending,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            $existingUser->notify(new ClubInvitationNotification($invitation));
        }

        return $invitation;
    }

    public function acceptInvitation(ClubInvitation $invitation, User $user): ClubMember
    {
        $invitation->update(['status' => InvitationStatus::Accepted]);

        return ClubMember::query()->firstOrCreate(
            [
                'club_id' => $invitation->club_id,
                'user_id' => $user->id,
            ],
            [
                'role' => ClubMemberRole::Player,
                'status' => ClubMemberStatus::Approved,
                'approved_at' => now(),
            ],
        );
    }

    public function joinViaLink(Club $club, User $user): ClubMember
    {
        $status = $club->requires_approval
            ? ClubMemberStatus::Pending
            : ClubMemberStatus::Approved;

        return ClubMember::query()->firstOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $user->id,
            ],
            [
                'role' => ClubMemberRole::Player,
                'status' => $status,
                'approved_at' => $status === ClubMemberStatus::Approved ? now() : null,
            ],
        );
    }
}
