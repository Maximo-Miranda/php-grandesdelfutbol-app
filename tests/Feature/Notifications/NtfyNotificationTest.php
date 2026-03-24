<?php

use App\Jobs\PublishClubNtfy;
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
use App\Services\NtfyService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use NotificationChannels\WebPush\WebPushChannel;

test('match registration open notification has correct ntfy payload', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notification = new MatchRegistrationOpenNotification($match);
    $payload = $notification->toNtfyPayload();

    expect($payload['title'])->toBe('Convocatoria abierta')
        ->and($payload['priority'])->toBe(4)
        ->and($payload['message'])->toContain($match->title)
        ->and($payload['click'])->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}");
});

test('match video uploaded notification has correct mail content', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notification = new MatchVideoUploadedNotification($match);
    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toContain($match->title)
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}/summary");
});

test('match stats finalized notification has correct ntfy payload', function () {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $notification = new MatchStatsFinalizedNotification($match);
    $payload = $notification->toNtfyPayload();

    expect($payload['title'])->toBe('Estadísticas disponibles')
        ->and($payload['priority'])->toBe(3)
        ->and($payload['message'])->toContain($match->title)
        ->and($payload['click'])->toContain("/clubs/{$club->ulid}/matches/{$match->ulid}/summary");
});

test('match notifications use web push channel', function () {
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
        expect($via)->toContain(WebPushChannel::class);
    }
});

test('ntfy service publishes to club topic', function () {
    Http::fake();

    config(['services.ntfy.url' => 'https://push.example.com']);
    config(['services.ntfy.token' => 'test-token']);

    $club = Club::factory()->create();

    $service = app(NtfyService::class);
    $service->publish($club, ['message' => 'Test']);

    Http::assertSent(function ($request) use ($club) {
        return $request->url() === 'https://push.example.com'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['topic'] === "gdf-{$club->ulid}"
            && $request['message'] === 'Test';
    });
});

test('ntfy service throws exception on http error', function () {
    Http::fake([
        '*' => Http::response('Unauthorized', 401),
    ]);

    $club = Club::factory()->create();

    $service = app(NtfyService::class);

    expect(fn () => $service->publish($club, ['message' => 'test']))
        ->toThrow(RequestException::class);
});

test('publish club ntfy job publishes to ntfy service', function () {
    Http::fake();

    $club = Club::factory()->create();
    $payload = ['message' => 'Test notification', 'title' => 'Test'];

    $job = new PublishClubNtfy($club, $payload);
    $job->handle(app(NtfyService::class));

    Http::assertSent(function ($request) use ($club) {
        return $request['topic'] === "gdf-{$club->ulid}"
            && $request['message'] === 'Test notification';
    });
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

test('club ntfy topic uses club ulid', function () {
    $club = Club::factory()->create();

    expect($club->ntfyTopic())->toBe("gdf-{$club->ulid}");
});
