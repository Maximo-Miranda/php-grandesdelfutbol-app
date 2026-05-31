<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\ProcessUploadedVideo;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Notification;

test('marks upload ready as original when s3 copy exists, without encoding', function () {
    Notification::fake();

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
        ->and($upload->best_resolution)->toBe('original')
        ->and($upload->encoded_at)->not->toBeNull()
        ->and($upload->drive_file_id)->toBe('drive-original')
        ->and($upload->s3_path)->toBe('uploads/test/original.mp4');
});

test('does nothing when there is no s3 copy and no drive file', function () {
    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        'status' => VideoUploadStatus::Encoding,
        's3_path' => null,
        'drive_file_id' => null,
    ]);

    (new ProcessUploadedVideo($upload))->handle();

    expect($upload->fresh()->status)->toBe(VideoUploadStatus::Encoding);
});
