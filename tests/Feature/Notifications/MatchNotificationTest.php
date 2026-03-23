<?php

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\ClubInvitationNotification;
use App\Notifications\MatchRegistrationOpenNotification;
use App\Notifications\MatchStatsFinalizedNotification;
use App\Notifications\MatchVideoUploadedNotification;
use App\Notifications\MemberApprovedNotification;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MemberRemovedNotification;
use App\Notifications\NewMemberRequestNotification;

test('all notifications use the notifications queue', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['club_id' => $club->id]);

    $notifications = [
        new MatchRegistrationOpenNotification($match),
        new MatchVideoUploadedNotification($match),
        new MatchStatsFinalizedNotification($match),
        new ClubInvitationNotification($invitation),
        new MemberApprovedNotification($club),
        new MemberRemovedNotification($club),
        new NewMemberRequestNotification($club, $user),
        new MemberLeftNotification($club, $user),
    ];

    foreach ($notifications as $notification) {
        expect($notification->queue)->toBe('notifications');
    }
});
