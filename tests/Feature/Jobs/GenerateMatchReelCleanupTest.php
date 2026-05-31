<?php

use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tempDir = storage_path('app/temp/reels');
    File::ensureDirectoryExists($this->tempDir);
});

afterEach(function () {
    if (File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

/**
 * Runs the real GenerateMatchReel::handle() — including its try/finally and the
 * real cleanupFiles() under test — while standing in for the two heavy steps
 * that need ffmpeg and the media library. ensureFullVideoDownloaded still runs
 * for real against a faked S3 disk, so the actual download path is exercised.
 */
function makeReelJob(MatchReel $reel): GenerateMatchReel
{
    return new class($reel) extends GenerateMatchReel
    {
        protected function cutSegment(string $sourceFile, string $outputFile): void
        {
            // Stand in for ffmpeg: produce the clip the real cut would have made.
            File::put($outputFile, str_repeat('c', 20_000));
        }

        protected function storeOutputAndComplete(string $outputFile): void
        {
            // Stand in for the media library upload: just mark the reel done.
            $this->reel->update(['status' => ReelStatus::Completed, 'processed_at' => now()]);
        }
    };
}

function seedReadyVideo(FootballMatch $match): void
{
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/source.mp4', str_repeat('s', 40_000));

    MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Ready,
        's3_reels_path' => 'videos/source.mp4',
        's3_path' => null,
        'drive_reels_file_id' => null,
        'drive_file_id' => null,
    ]);
}

test('handle deletes the downloaded source after a successful reel when none pending', function () {
    $match = FootballMatch::factory()->completed()->create();
    seedReadyVideo($match);

    $reel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'status' => ReelStatus::Pending,
        'start_second' => 10,
        'end_second' => 35,
    ]);

    makeReelJob($reel)->handle();

    $source = $this->tempDir."/full-{$match->ulid}.mp4";
    $output = $this->tempDir.'/'.$reel->ulid.'.mp4';

    expect($reel->fresh()->status)->toBe(ReelStatus::Completed)
        ->and(File::exists($source))->toBeFalse() // the disk-leak bug: used to stay forever
        ->and(File::exists($output))->toBeFalse();
});

test('handle keeps the downloaded source when other reels are still pending', function () {
    $match = FootballMatch::factory()->completed()->create();
    seedReadyVideo($match);

    $reel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'status' => ReelStatus::Pending,
        'start_second' => 10,
        'end_second' => 35,
    ]);
    MatchReel::factory()->create(['match_id' => $match->id, 'status' => ReelStatus::Pending]);

    makeReelJob($reel)->handle();

    $source = $this->tempDir."/full-{$match->ulid}.mp4";
    $output = $this->tempDir.'/'.$reel->ulid.'.mp4';

    // The shared source is reused by the still-pending reel; only this clip is removed.
    expect($reel->fresh()->status)->toBe(ReelStatus::Completed)
        ->and($output)->not->toBeFile()
        ->and(File::exists($source))->toBeTrue();
});
