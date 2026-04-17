<?php

use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;

test('marks encoding uploads stuck for more than threshold as failed', function () {
    $match = FootballMatch::factory()->completed()->create();
    $stuck = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Encoding,
        'updated_at' => now()->subMinutes(45),
    ]);

    $this->artisan('videos:recover-stuck', ['--minutes' => 30])
        ->assertSuccessful();

    expect($stuck->fresh()->status)->toBe(VideoUploadStatus::Failed)
        ->and($stuck->fresh()->error_message)->toContain('Reintentá');
});

test('leaves fresh uploads untouched', function () {
    $match = FootballMatch::factory()->completed()->create();
    $fresh = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Encoding,
        'updated_at' => now()->subMinutes(5),
    ]);

    $this->artisan('videos:recover-stuck', ['--minutes' => 30])
        ->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(VideoUploadStatus::Encoding);
});

test('leaves ready uploads untouched', function () {
    $match = FootballMatch::factory()->completed()->create();
    $ready = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'updated_at' => now()->subDays(2),
    ]);

    $this->artisan('videos:recover-stuck', ['--minutes' => 30])
        ->assertSuccessful();

    expect($ready->fresh()->status)->toBe(VideoUploadStatus::Ready);
});

test('stuck Uploading (never confirmed) also recovered', function () {
    $match = FootballMatch::factory()->completed()->create();
    $stuckUploading = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Uploading,
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('videos:recover-stuck', ['--minutes' => 30])
        ->assertSuccessful();

    expect($stuckUploading->fresh()->status)->toBe(VideoUploadStatus::Failed);
});
