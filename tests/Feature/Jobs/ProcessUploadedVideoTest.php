<?php

use App\Enums\VideoProcessingStage;
use App\Enums\VideoResolution;
use App\Enums\VideoUploadStatus;
use App\Jobs\ProcessUploadedVideo;
use App\Jobs\UploadMatchToYouTube;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

test('marks upload ready as original when s3 copy exists, without encoding', function () {
    Notification::fake();
    Bus::fake();

    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        'status' => VideoUploadStatus::Encoding,
        's3_path' => 'uploads/test/original.mp4',
        'original_s3_path' => 'uploads/test/original.mp4',
        'drive_file_id' => 'drive-original',
        'drive_shared_at' => now(),
        'best_resolution' => null,
        'encoded_at' => null,
    ]);

    (new ProcessUploadedVideo($upload))->handle();

    $upload->refresh();

    expect($upload->status)->toBe(VideoUploadStatus::Ready)
        ->and($upload->best_resolution)->toBe(VideoResolution::Original)
        ->and($upload->encoded_at)->not->toBeNull()
        ->and($upload->drive_file_id)->toBe('drive-original')
        ->and($upload->s3_path)->toBe('uploads/test/original.mp4');
});

test('chains the youtube upload once the original is on s3', function () {
    Notification::fake();
    Bus::fake();

    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        'status' => VideoUploadStatus::Encoding,
        's3_path' => 'uploads/test/original.mp4',
        'drive_file_id' => 'drive-original',
        'drive_shared_at' => now(),
        'youtube_video_id' => null,
    ]);

    (new ProcessUploadedVideo($upload))->handle();

    Bus::assertDispatched(UploadMatchToYouTube::class);
    expect($upload->fresh()->processing_stage)->toBe(VideoProcessingStage::Publishing);
});

test('does not re-dispatch youtube when already uploaded', function () {
    Notification::fake();
    Bus::fake();

    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        'status' => VideoUploadStatus::Encoding,
        's3_path' => 'uploads/test/original.mp4',
        'drive_file_id' => 'drive-original',
        'drive_shared_at' => now(),
        'youtube_video_id' => 'already-on-youtube',
    ]);

    (new ProcessUploadedVideo($upload))->handle();

    Bus::assertNotDispatched(UploadMatchToYouTube::class);
});

test('does nothing when there is no s3 copy and no drive file', function () {
    Bus::fake();

    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        'status' => VideoUploadStatus::Encoding,
        's3_path' => null,
        'drive_file_id' => null,
    ]);

    (new ProcessUploadedVideo($upload))->handle();

    expect($upload->fresh()->status)->toBe(VideoUploadStatus::Encoding);
    Bus::assertNotDispatched(UploadMatchToYouTube::class);
});
