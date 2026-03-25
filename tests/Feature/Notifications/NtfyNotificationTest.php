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
use NotificationChannels\WebPush\WebPushChannel;

test('match video uploaded notification has correct mail content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notification = new MatchVideoUploadedNotification($match);
    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toContain($match->title)
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}/summary");
});

test('match registration open notification has correct mail content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notification = new MatchRegistrationOpenNotification($match);
    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toContain($match->title)
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}");
});

test('match stats finalized notification has correct mail content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notification = new MatchStatsFinalizedNotification($match);
    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toContain($match->title)
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}/summary");
});

test('match notifications always include mail channel', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->create();

    $notifications = [
        new MatchRegistrationOpenNotification($match),
        new MatchVideoUploadedNotification($match),
        new MatchStatsFinalizedNotification($match),
    ];

    foreach ($notifications as $notification) {
        $via = $notification->via($user);
        expect($via)->toContain('mail');
    }
});

test('match notifications include web push only for users with subscriptions', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $userWithPush = User::factory()->create();
    $userWithPush->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

    $userWithout = User::factory()->create();

    $notifications = [
        new MatchRegistrationOpenNotification($match),
        new MatchVideoUploadedNotification($match),
        new MatchStatsFinalizedNotification($match),
    ];

    foreach ($notifications as $notification) {
        expect($notification->via($userWithPush))->toContain(WebPushChannel::class)
            ->and($notification->via($userWithout))->not->toContain(WebPushChannel::class);
    }
});

test('all three match notifications use notifications queue', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notifications = [
        new MatchRegistrationOpenNotification($match),
        new MatchVideoUploadedNotification($match),
        new MatchStatsFinalizedNotification($match),
    ];

    foreach ($notifications as $notification) {
        expect($notification->queue)->toBe('notifications')
            ->and($notification->tries)->toBe(3)
            ->and($notification->backoff)->toBe([10, 30])
            ->and($notification->afterCommit)->toBeTrue();
    }
});

test('original notifications only use mail channel', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['club_id' => $club->id]);

    $notifications = [
        new ClubInvitationNotification($invitation),
        new MemberApprovedNotification($club),
        new MemberRemovedNotification($club),
        new NewMemberRequestNotification($club, $user),
        new MemberLeftNotification($club, $user),
    ];

    foreach ($notifications as $notification) {
        $via = $notification->via($user);
        expect($via)->toBe(['mail'])
            ->and($via)->not->toContain(WebPushChannel::class);
    }
});
