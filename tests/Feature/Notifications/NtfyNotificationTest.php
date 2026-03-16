<?php

use App\Channels\NtfyChannel;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchRegistrationOpenNotification;
use App\Notifications\MatchStatsFinalizedNotification;
use App\Notifications\MatchVideoUploadedNotification;
use Illuminate\Support\Facades\Http;

test('ntfy notification is sent when user has ntfy enabled', function () {
    Http::fake();

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->withNtfy()->create();

    $notification = new MatchRegistrationOpenNotification($match);
    $channel = app(NtfyChannel::class);
    $channel->send($user, $notification);

    Http::assertSentCount(1);
});

test('ntfy notification is not sent when user has ntfy disabled', function () {
    Http::fake();

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->create();

    expect($user->hasNtfyEnabled())->toBeFalse();

    $notification = new MatchRegistrationOpenNotification($match);
    $channel = app(NtfyChannel::class);
    $channel->send($user, $notification);

    Http::assertNothingSent();
});

test('match registration open notification has correct ntfy content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->withNtfy()->create();

    $notification = new MatchRegistrationOpenNotification($match);
    $message = $notification->toNtfy($user);

    expect($message->getTitle())->toBe('Confirma tu asistencia')
        ->and($message->getPriority())->toBe(4)
        ->and($message->getBody())->toContain($match->title)
        ->and($message->toArray()['click'])->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}");
});

test('match video uploaded notification has correct ntfy content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->withNtfy()->create();

    $notification = new MatchVideoUploadedNotification($match);
    $message = $notification->toNtfy($user);

    expect($message->getTitle())->toBe('Video disponible')
        ->and($message->getPriority())->toBe(3)
        ->and($message->getBody())->toContain($match->title)
        ->and($message->toArray()['click'])->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}/summary");
});

test('match stats finalized notification has correct ntfy content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->withNtfy()->create();

    $notification = new MatchStatsFinalizedNotification($match);
    $message = $notification->toNtfy($user);

    expect($message->getTitle())->toBe('Estadísticas registradas')
        ->and($message->getPriority())->toBe(3)
        ->and($message->getBody())->toContain($match->title)
        ->and($message->toArray()['click'])->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}/summary");
});

test('match notifications only use ntfy channel', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $user = User::factory()->withNtfy()->create();

    $notifications = [
        new MatchRegistrationOpenNotification($match),
        new MatchVideoUploadedNotification($match),
        new MatchStatsFinalizedNotification($match),
    ];

    foreach ($notifications as $notification) {
        $via = $notification->via($user);
        expect($via)->toBe([NtfyChannel::class])
            ->and($via)->not->toContain('mail');
    }
});

test('original notifications only use mail channel', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    $invitation = \App\Models\ClubInvitation::factory()->create(['club_id' => $club->id]);

    $notifications = [
        new \App\Notifications\ClubInvitationNotification($invitation),
        new \App\Notifications\MemberApprovedNotification($club),
        new \App\Notifications\MemberRemovedNotification($club),
        new \App\Notifications\NewMemberRequestNotification($club, $user),
        new \App\Notifications\MemberLeftNotification($club, $user),
    ];

    foreach ($notifications as $notification) {
        $via = $notification->via($user);
        expect($via)->toBe(['mail'])
            ->and($via)->not->toContain(NtfyChannel::class);
    }
});
