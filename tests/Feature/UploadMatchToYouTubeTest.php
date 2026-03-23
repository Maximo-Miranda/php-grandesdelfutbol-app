<?php

use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('skips upload when youtube_video_id already exists', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'existing-id',
        's3_path' => 'uploads/test.mp4',
    ]);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldNotReceive('uploadVideo');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    expect($upload->fresh()->youtube_video_id)->toBe('existing-id');
});

it('skips upload when youtube is not configured', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
        's3_path' => 'uploads/test.mp4',
    ]);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(false);
    $youtubeMock->shouldNotReceive('uploadVideo');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    expect($upload->fresh()->youtube_video_id)->toBeNull();
});

it('downloads from s3 and uploads to youtube', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('uploads/test.mp4', 'fake-video-content');

    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
        's3_path' => 'uploads/test.mp4',
    ]);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldReceive('uploadVideo')
        ->once()
        ->andReturn('yt-new-video-id');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    $upload->refresh();

    expect($upload->youtube_video_id)->toBe('yt-new-video-id')
        ->and($upload->youtube_uploaded_at)->not->toBeNull();
});

it('is dispatched on video-processing queue', function () {
    $upload = MatchVideoUpload::factory()->create();

    $job = new UploadMatchToYouTube($upload);

    expect($job->queue)->toBe('video-processing');
});
