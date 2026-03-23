<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\ProcessEncodedVideo;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('handles status 3 (finished) and dispatches pipeline', function () {
    Queue::fake();
    Http::fake([
        'video.bunnycdn.com/*' => Http::response([
            'guid' => 'test-video-id',
            'length' => 3600,
            'encodeProgress' => 100,
        ]),
    ]);

    $upload = MatchVideoUpload::factory()->encoding()->create([
        'bunny_video_id' => 'test-video-id',
    ]);

    $this->postJson('/api/webhooks/bunny', [
        'VideoGuid' => 'test-video-id',
        'Status' => 3,
    ])->assertSuccessful();

    $upload->refresh();

    expect($upload->status)->toBe(VideoUploadStatus::Ready)
        ->and($upload->encoded_at)->not->toBeNull()
        ->and($upload->duration_seconds)->toBe(3600);

    Queue::assertPushed(ProcessEncodedVideo::class);
});

it('ignores status 4 (single resolution finished)', function () {
    Queue::fake();

    $upload = MatchVideoUpload::factory()->encoding()->create([
        'bunny_video_id' => 'test-video-id',
    ]);

    $this->postJson('/api/webhooks/bunny', [
        'VideoGuid' => 'test-video-id',
        'Status' => 4,
    ])->assertSuccessful();

    $upload->refresh();

    expect($upload->status)->toBe(VideoUploadStatus::Encoding);

    Queue::assertNotPushed(ProcessEncodedVideo::class);
});

it('delays pipeline when encodeProgress is not 100', function () {
    Queue::fake();
    Http::fake([
        'video.bunnycdn.com/*' => Http::response([
            'guid' => 'test-video-id',
            'length' => 0,
            'encodeProgress' => 75,
        ]),
    ]);

    $upload = MatchVideoUpload::factory()->encoding()->create([
        'bunny_video_id' => 'test-video-id',
    ]);

    $this->postJson('/api/webhooks/bunny', [
        'VideoGuid' => 'test-video-id',
        'Status' => 3,
    ])->assertSuccessful();

    $upload->refresh();

    // Should NOT be marked as Ready yet
    expect($upload->status)->toBe(VideoUploadStatus::Encoding);

    // But should dispatch with delay for retry
    Queue::assertPushed(ProcessEncodedVideo::class);
});

it('handles status 5 (failed)', function () {
    $upload = MatchVideoUpload::factory()->encoding()->create([
        'bunny_video_id' => 'test-video-id',
    ]);

    $this->postJson('/api/webhooks/bunny', [
        'VideoGuid' => 'test-video-id',
        'Status' => 5,
    ])->assertSuccessful();

    $upload->refresh();

    expect($upload->status)->toBe(VideoUploadStatus::Failed)
        ->and($upload->error_message)->not->toBeNull();
});

it('skips if video is already ready', function () {
    Queue::fake();

    $upload = MatchVideoUpload::factory()->ready()->create([
        'bunny_video_id' => 'test-video-id',
    ]);

    $this->postJson('/api/webhooks/bunny', [
        'VideoGuid' => 'test-video-id',
        'Status' => 3,
    ])->assertSuccessful();

    Queue::assertNotPushed(ProcessEncodedVideo::class);
});

it('returns 404 for unknown video', function () {
    $this->postJson('/api/webhooks/bunny', [
        'VideoGuid' => 'non-existent',
        'Status' => 3,
    ])->assertNotFound();
});

it('returns 400 when VideoGuid is missing', function () {
    $this->postJson('/api/webhooks/bunny', [
        'Status' => 3,
    ])->assertStatus(400);
});
