<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\PublishClubNtfy;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use App\Notifications\MatchVideoUploadedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('webhook dispatches notifications when video encoding completes', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $videoUpload = MatchVideoUpload::factory()->encoding()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $admin->id,
        'bunny_video_id' => 'test-bunny-video-id',
    ]);

    $memberWithPush = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithPush->id]);
    $memberWithPush->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

    Http::fake([
        'video.bunnycdn.com/*' => Http::response(['length' => 3600]),
    ]);

    $this->postJson(route('webhooks.bunny'), [
        'VideoGuid' => 'test-bunny-video-id',
        'Status' => 4,
    ])->assertOk();

    expect($videoUpload->fresh()->status)->toBe(VideoUploadStatus::Ready);
    expect($videoUpload->fresh()->duration_seconds)->toBe(3600);

    Notification::assertSentTo($memberWithPush, MatchVideoUploadedNotification::class);
    Bus::assertDispatched(PublishClubNtfy::class);
});

test('webhook handles encoding failure', function () {
    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $videoUpload = MatchVideoUpload::factory()->encoding()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $admin->id,
        'bunny_video_id' => 'failed-video-id',
    ]);

    $this->postJson(route('webhooks.bunny'), [
        'VideoGuid' => 'failed-video-id',
        'Status' => 5,
    ])->assertOk();

    expect($videoUpload->fresh()->status)->toBe(VideoUploadStatus::Failed);
    expect($videoUpload->fresh()->error_message)->not->toBeNull();
});

test('webhook returns 404 for unknown video', function () {
    $this->postJson(route('webhooks.bunny'), [
        'VideoGuid' => 'nonexistent-video',
        'Status' => 4,
    ])->assertNotFound();
});

test('webhook returns 400 without VideoGuid', function () {
    $this->postJson(route('webhooks.bunny'), [
        'Status' => 4,
    ])->assertStatus(400);
});
