<?php

use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use App\Services\YouTubeService;

use function Pest\Laravel\mock;

it('skips upload when youtube_video_id already exists', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'existing-id',
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldNotReceive('downloadHighestQuality');

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldNotReceive('uploadVideo');

    (new UploadMatchToYouTube($upload))->handle($bunnyMock, $youtubeMock);

    expect($upload->fresh()->youtube_video_id)->toBe('existing-id');
});

it('skips upload when youtube is not configured', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldNotReceive('downloadHighestQuality');

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(false);
    $youtubeMock->shouldNotReceive('uploadVideo');

    (new UploadMatchToYouTube($upload))->handle($bunnyMock, $youtubeMock);

    expect($upload->fresh()->youtube_video_id)->toBeNull();
});

it('downloads from bunny and uploads to youtube', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldReceive('downloadHighestQuality')
        ->once()
        ->andReturnUsing(function (string $videoId, string $outputPath) {
            // Create a fake temp file so cleanup works
            file_put_contents($outputPath, 'fake-video');

            return '1080p';
        });

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldReceive('uploadVideo')
        ->once()
        ->andReturn('yt-new-video-id');

    (new UploadMatchToYouTube($upload))->handle($bunnyMock, $youtubeMock);

    $upload->refresh();

    expect($upload->youtube_video_id)->toBe('yt-new-video-id')
        ->and($upload->youtube_uploaded_at)->not->toBeNull()
        ->and($upload->best_resolution)->toBe('1080p');
});

it('cleans up temp file even on failure', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
    ]);

    $tempFile = storage_path('app/temp/youtube/'.$upload->ulid.'.mp4');

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldReceive('downloadHighestQuality')
        ->andReturnUsing(function (string $videoId, string $outputPath) {
            file_put_contents($outputPath, 'fake-video');

            return '720p';
        });

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldReceive('uploadVideo')->andThrow(new RuntimeException('Upload failed'));

    try {
        (new UploadMatchToYouTube($upload))->handle($bunnyMock, $youtubeMock);
    } catch (RuntimeException) {
        // Expected
    }

    expect(file_exists($tempFile))->toBeFalse();
});

it('is dispatched on video-processing queue', function () {
    $upload = MatchVideoUpload::factory()->create();

    $job = new UploadMatchToYouTube($upload);

    expect($job->queue)->toBe('video-processing');
});
