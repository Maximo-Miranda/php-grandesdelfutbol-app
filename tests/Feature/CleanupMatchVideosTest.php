<?php

use App\Console\Commands\CleanupMatchVideos;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

test('deletes s3 copy after retention period when backed up on youtube and drive', function () {
    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        's3_path' => 'videos/matches/test/original.mp4',
        'original_s3_path' => 'videos/matches/test/original.mp4',
        'encoded_at' => now()->subDays(10),
        'youtube_video_id' => 'abc123',
        'drive_file_id' => 'drive-original-id',
    ]);

    Storage::disk('s3')->put('videos/matches/test/original.mp4', 'fake content');

    $this->artisan(CleanupMatchVideos::class)
        ->assertSuccessful();

    expect(Storage::disk('s3')->exists('videos/matches/test/original.mp4'))->toBeFalse()
        ->and($upload->fresh()->s3_path)->toBeNull()
        ->and($upload->fresh()->original_s3_path)->toBeNull();
});

test('keeps s3 copy within retention period', function () {
    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        's3_path' => 'videos/matches/test/original.mp4',
        'encoded_at' => now()->subDays(2),
        'youtube_video_id' => 'abc123',
        'drive_file_id' => 'drive-original-id',
    ]);

    Storage::disk('s3')->put('videos/matches/test/original.mp4', 'fake content');

    $this->artisan(CleanupMatchVideos::class)
        ->assertSuccessful();

    expect(Storage::disk('s3')->exists('videos/matches/test/original.mp4'))->toBeTrue()
        ->and($upload->fresh()->s3_path)->not->toBeNull();
});

test('skips videos without youtube backup', function () {
    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        's3_path' => 'videos/matches/test/original.mp4',
        'encoded_at' => now()->subDays(10),
        'youtube_video_id' => null,
        'drive_file_id' => 'drive-original-id',
    ]);

    Storage::disk('s3')->put('videos/matches/test/original.mp4', 'fake content');

    $this->artisan(CleanupMatchVideos::class)
        ->assertSuccessful();

    expect(Storage::disk('s3')->exists('videos/matches/test/original.mp4'))->toBeTrue()
        ->and($upload->fresh()->s3_path)->not->toBeNull();
});

test('skips videos without drive backup', function () {
    $match = FootballMatch::factory()->create();
    $upload = MatchVideoUpload::factory()->for($match, 'match')->create([
        's3_path' => 'videos/matches/test/original.mp4',
        'encoded_at' => now()->subDays(10),
        'youtube_video_id' => 'abc123',
        'drive_file_id' => null,
    ]);

    Storage::disk('s3')->put('videos/matches/test/original.mp4', 'fake content');

    $this->artisan(CleanupMatchVideos::class)
        ->assertSuccessful();

    expect(Storage::disk('s3')->exists('videos/matches/test/original.mp4'))->toBeTrue()
        ->and($upload->fresh()->s3_path)->not->toBeNull();
});
