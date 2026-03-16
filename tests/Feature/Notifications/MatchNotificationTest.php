<?php

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchRegistrationOpenNotification;
use App\Notifications\MatchStatsFinalizedNotification;
use App\Notifications\MatchVideoUploadedNotification;

test('all notifications use the notifications queue', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->create();
    $invitation = \App\Models\ClubInvitation::factory()->create(['club_id' => $club->id]);

    $notifications = [
        new MatchRegistrationOpenNotification($match),
        new MatchVideoUploadedNotification($match),
        new MatchStatsFinalizedNotification($match),
        new \App\Notifications\ClubInvitationNotification($invitation),
        new \App\Notifications\MemberApprovedNotification($club),
        new \App\Notifications\MemberRemovedNotification($club),
        new \App\Notifications\NewMemberRequestNotification($club, $user),
        new \App\Notifications\MemberLeftNotification($club, $user),
    ];

    foreach ($notifications as $notification) {
        expect($notification->queue)->toBe('notifications');
    }
});
