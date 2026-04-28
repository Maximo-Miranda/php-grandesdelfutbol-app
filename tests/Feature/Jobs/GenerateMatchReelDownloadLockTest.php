<?php

use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tempDir = storage_path('app/temp/reels-test-'.uniqid());
    File::ensureDirectoryExists($this->tempDir);
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

test('ensureFullVideoDownloaded returns existing complete file without re-downloading', function () {
    $match = FootballMatch::factory()->create();
    $videoUpload = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        's3_reels_path' => 'videos/should-not-be-touched.mp4',
    ]);

    $localPath = $this->tempDir."/full-{$match->ulid}.mp4";
    file_put_contents($localPath, str_repeat('x', 50_000));

    Storage::fake('s3');

    $reel = MatchReel::factory()->create(['match_id' => $match->id]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'ensureFullVideoDownloaded');
    $result = $method->invoke($job, $match, $videoUpload, $this->tempDir);

    expect($result)->toBe($localPath)
        ->and(File::size($localPath))->toBe(50_000);
});

test('ensureFullVideoDownloaded writes via partial then renames atomically', function () {
    $match = FootballMatch::factory()->create();

    Storage::fake('s3');
    Storage::disk('s3')->put('videos/source.mp4', str_repeat('A', 20_000));

    $videoUpload = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        's3_reels_path' => 'videos/source.mp4',
    ]);

    $reel = MatchReel::factory()->create(['match_id' => $match->id]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'ensureFullVideoDownloaded');
    $result = $method->invoke($job, $match, $videoUpload, $this->tempDir);

    $localPath = $this->tempDir."/full-{$match->ulid}.mp4";
    $partialPath = $localPath.'.partial';

    expect($result)->toBe($localPath)
        ->and(File::exists($localPath))->toBeTrue()
        ->and(File::exists($partialPath))->toBeFalse()
        ->and(File::size($localPath))->toBe(20_000);
});

test('ensureFullVideoDownloaded cleans up partial file when download fails', function () {
    $match = FootballMatch::factory()->create();
    $videoUpload = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        's3_reels_path' => null,
        's3_path' => null,
        'drive_reels_file_id' => null,
        'drive_file_id' => null,
    ]);

    Storage::fake('s3');

    $reel = MatchReel::factory()->create(['match_id' => $match->id]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'ensureFullVideoDownloaded');

    expect(fn () => $method->invoke($job, $match, $videoUpload, $this->tempDir))
        ->toThrow(RuntimeException::class, 'No se encontró ninguna fuente');

    $localPath = $this->tempDir."/full-{$match->ulid}.mp4";
    expect(File::exists($localPath))->toBeFalse()
        ->and(File::exists($localPath.'.partial'))->toBeFalse();
});
