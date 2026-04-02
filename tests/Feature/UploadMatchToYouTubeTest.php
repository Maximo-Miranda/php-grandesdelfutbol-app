<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeService;
use Google\Service\Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('skips upload when youtube_video_id already exists', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'existing-id',
        's3_path' => 'uploads/test.mp4',
    ]);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldNotReceive('uploadVideo');

    new UploadMatchToYouTube($upload)->handle($youtubeMock);

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

it('sets status to ready with error message when youtube fails and video is encoded', function () {
    $upload = MatchVideoUpload::factory()->ready()->create([
        'best_resolution' => '1080p',
        's3_path' => 'videos/test/1080p.mp4',
        'youtube_video_id' => null,
    ]);

    $job = new UploadMatchToYouTube($upload);
    $exception = new Exception('uploadLimitExceeded', 400);

    $job->failed($exception);

    $upload->refresh();

    expect($upload->status)->toBe(VideoUploadStatus::Ready)
        ->and($upload->error_message)->toContain('límite diario de YouTube')
        ->and($upload->error_message)->toContain('Reintentar');
});

it('sets error message when daily limit is reached at job execution time', function () {
    Cache::put('youtube-daily-uploads:'.now()->format('Y-m-d'), 6, now()->endOfDay());

    $upload = MatchVideoUpload::factory()->ready()->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        's3_path' => 'videos/test/1080p.mp4',
    ]);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldNotReceive('uploadVideo');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    $upload->refresh();

    expect($upload->youtube_video_id)->toBeNull()
        ->and($upload->error_message)->toContain('Límite diario');
});

it('sets status to failed when youtube fails and video has no resolution', function () {
    $upload = MatchVideoUpload::factory()->encoding()->create([
        'best_resolution' => null,
        's3_path' => 'uploads/original.mp4',
        'youtube_video_id' => null,
    ]);

    $job = new UploadMatchToYouTube($upload);
    $exception = new RuntimeException('Connection timeout');

    $job->failed($exception);

    $upload->refresh();

    expect($upload->status)->toBe(VideoUploadStatus::Failed)
        ->and($upload->error_message)->toContain('Error al subir a YouTube');
});
