<?php

namespace App\Services;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\InvitationStatus;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;
use App\Notifications\ClubInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(private ClubService $clubService) {}

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
        } else {
            Notification::route('mail', $email)
                ->notify(new ClubInvitationNotification($invitation));
        }

        return $invitation;
    }

    public function acceptInvitation(ClubInvitation $invitation, User $user): ClubMember
    {
        return DB::transaction(function () use ($invitation, $user) {
            $invitation->update(['status' => InvitationStatus::Accepted]);

            $member = ClubMember::query()->firstOrCreate(
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

            $this->ensurePlayerExists($invitation->club_id, $user);

            $this->clubService->switchToClub($user, $invitation->club);

            return $member;
        });
    }

    public function joinViaLink(Club $club, User $user): ClubMember
    {
        return DB::transaction(function () use ($club, $user) {
            $status = $club->requires_approval
                ? ClubMemberStatus::Pending
                : ClubMemberStatus::Approved;

            $member = ClubMember::query()->firstOrCreate(
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

            if ($status === ClubMemberStatus::Approved) {
                $this->ensurePlayerExists($club->id, $user);
                $this->clubService->switchToClub($user, $club);
            }

            return $member;
        });
    }

    /**
     * Ensure a Player record exists for this user in this club.
     */
    private function ensurePlayerExists(int $clubId, User $user): Player
    {
        return Player::query()->firstOrCreate(
            [
                'club_id' => $clubId,
                'user_id' => $user->id,
            ],
            [
                'name' => $user->name,
                'is_active' => true,
            ],
        );
    }
}
