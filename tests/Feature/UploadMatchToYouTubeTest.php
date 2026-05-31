<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeService;
use Google\Service\Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('skips upload but adds to playlist when youtube_video_id already exists', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'existing-id',
        's3_path' => 'uploads/test.mp4',
    ]);

    $club = $upload->match->club;
    $club->update(['youtube_playlist_id' => 'pl-existing']);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldNotReceive('uploadVideo');
    $youtubeMock->shouldReceive('playlistExists')->with('pl-existing')->andReturn(true);
    $youtubeMock->shouldReceive('addToPlaylist')
        ->once()
        ->with('pl-existing', 'existing-id');

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
    $youtubeMock->shouldReceive('playlistExists')->andReturn(false);
    $youtubeMock->shouldReceive('createPlaylist')->andReturn('pl-id');
    $youtubeMock->shouldReceive('addToPlaylist');
    $youtubeMock->shouldReceive('uploadVideo')
        ->once()
        ->andReturn('yt-new-video-id');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    $upload->refresh();

    expect($upload->youtube_video_id)->toBe('yt-new-video-id')
        ->and($upload->youtube_uploaded_at)->not->toBeNull();
});

it('creates playlist and adds video when club has no playlist', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('uploads/test.mp4', 'fake-video-content');

    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
        's3_path' => 'uploads/test.mp4',
    ]);

    $club = $upload->match->club;
    $club->update(['youtube_playlist_id' => null]);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldReceive('playlistExists')->andReturn(true);
    $youtubeMock->shouldReceive('uploadVideo')->once()->andReturn('yt-video-123');
    $youtubeMock->shouldReceive('createPlaylist')
        ->once()
        ->with($club->name, "Partidos de {$club->name} - Grandes del Futbol")
        ->andReturn('pl-new-playlist');
    $youtubeMock->shouldReceive('addToPlaylist')
        ->once()
        ->with('pl-new-playlist', 'yt-video-123');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    expect($club->fresh()->youtube_playlist_id)->toBe('pl-new-playlist');
});

it('adds video to existing playlist when club already has one', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('uploads/test.mp4', 'fake-video-content');

    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
        's3_path' => 'uploads/test.mp4',
    ]);

    $club = $upload->match->club;
    $club->update(['youtube_playlist_id' => 'pl-existing']);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldReceive('playlistExists')->with('pl-existing')->andReturn(true);
    $youtubeMock->shouldReceive('uploadVideo')->once()->andReturn('yt-video-456');
    $youtubeMock->shouldNotReceive('createPlaylist');
    $youtubeMock->shouldReceive('addToPlaylist')
        ->once()
        ->with('pl-existing', 'yt-video-456');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    expect($club->fresh()->youtube_playlist_id)->toBe('pl-existing');
});

it('recreates playlist when stored playlist was deleted from youtube', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('uploads/test.mp4', 'fake-video-content');

    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
        's3_path' => 'uploads/test.mp4',
    ]);

    $club = $upload->match->club;
    $club->update(['youtube_playlist_id' => 'pl-deleted-from-youtube']);

    $youtubeMock = mock(YouTubeService::class);
    $youtubeMock->shouldReceive('isConfigured')->andReturn(true);
    $youtubeMock->shouldReceive('playlistExists')->with('pl-deleted-from-youtube')->andReturn(false);
    $youtubeMock->shouldReceive('playlistExists')->with('pl-recreated')->andReturn(true);
    $youtubeMock->shouldReceive('createPlaylist')
        ->once()
        ->with($club->name, "Partidos de {$club->name} - Grandes del Futbol")
        ->andReturn('pl-recreated');
    $youtubeMock->shouldReceive('uploadVideo')->once()->andReturn('yt-video-789');
    $youtubeMock->shouldReceive('addToPlaylist')
        ->once()
        ->with('pl-recreated', 'yt-video-789');

    (new UploadMatchToYouTube($upload))->handle($youtubeMock);

    expect($club->fresh()->youtube_playlist_id)->toBe('pl-recreated');
});

it('is dispatched on youtube queue', function () {
    $upload = MatchVideoUpload::factory()->create();

    $job = new UploadMatchToYouTube($upload);

    expect($job->queue)->toBe('youtube');
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
