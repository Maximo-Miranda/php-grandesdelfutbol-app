<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\UploadMatchToYouTube;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['youtube.daily_upload_limit' => 6]);
    Queue::fake();
});

function pendingUpload(): MatchVideoUpload
{
    return MatchVideoUpload::factory()->create([
        'football_match_id' => FootballMatch::factory()->completed()->create()->id,
        'status' => VideoUploadStatus::Ready,
        'youtube_video_id' => null,
        's3_path' => 'uploads/test/original.mp4',
        'drive_file_id' => 'drive-original',
    ]);
}

test('re-dispatches ready videos that have no youtube id yet', function () {
    $upload = pendingUpload();

    $this->artisan('videos:retry-youtube-pending')->assertSuccessful();

    Queue::assertPushed(UploadMatchToYouTube::class);
    expect($upload->fresh()->youtube_upload_requested_at)->not->toBeNull();
});

test('does not re-dispatch videos already on youtube', function () {
    MatchVideoUpload::factory()->create([
        'football_match_id' => FootballMatch::factory()->completed()->create()->id,
        'status' => VideoUploadStatus::Ready,
        'youtube_video_id' => 'already-up',
        's3_path' => 'uploads/test/original.mp4',
    ]);

    $this->artisan('videos:retry-youtube-pending')->assertSuccessful();

    Queue::assertNotPushed(UploadMatchToYouTube::class);
});

test('respects available quota: only re-dispatches up to the daily limit', function () {
    config(['youtube.daily_upload_limit' => 2]);

    foreach (range(1, 5) as $i) {
        pendingUpload();
    }

    $this->artisan('videos:retry-youtube-pending')->assertSuccessful();

    Queue::assertPushed(UploadMatchToYouTube::class, 2);
});

test('does nothing when quota is exhausted', function () {
    config(['youtube.daily_upload_limit' => 1]);
    Cache::put('youtube-daily-uploads:'.now()->format('Y-m-d'), 1, now()->endOfDay());

    pendingUpload();

    $this->artisan('videos:retry-youtube-pending')->assertSuccessful();

    Queue::assertNotPushed(UploadMatchToYouTube::class);
});
