<?php

use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempDir = storage_path('app/temp/reels');
    File::ensureDirectoryExists($this->tempDir);
});

afterEach(function () {
    if (File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

function runCleanup(GenerateMatchReel $job, string $output, ?string $source, FootballMatch $match): void
{
    (new ReflectionMethod($job, 'cleanupFiles'))->invoke($job, $output, $source, $match);
}

test('handle deletes the downloaded source after a SUCCESSFUL reel (the disk-leak bug)', function () {
    $match = FootballMatch::factory()->completed()->create();
    MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Ready,
        's3_reels_path' => null,
        's3_path' => null,
        'drive_reels_file_id' => null,
        'drive_file_id' => 'drive-original',
    ]);

    $reel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'status' => ReelStatus::Pending,
        'start_second' => 10,
        'end_second' => 35,
    ]);

    $source = $this->tempDir."/full-{$match->ulid}.mp4";

    // Partial-mock the heavy steps so we exercise the real handle() control flow
    // (and its finally) without ffmpeg or the media library: the download just
    // pre-places the source file, the cut is a no-op, and storing marks the reel
    // completed. The assertion targets only the source cleanup on success — the
    // bug was that handle() never deleted the multi-GB source on the happy path.
    $job = Mockery::mock(GenerateMatchReel::class, [$reel])->makePartial();
    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('ensureFullVideoDownloaded')->once()->andReturnUsing(function () use ($source) {
        File::put($source, str_repeat('x', 50_000));

        return $source;
    });
    $job->shouldReceive('cutSegment')->once();
    $job->shouldReceive('storeOutputAndComplete')->once()->andReturnUsing(function () use ($reel) {
        $reel->update(['status' => ReelStatus::Completed, 'processed_at' => now()]);
    });

    $job->handle();

    expect($reel->fresh()->status)->toBe(ReelStatus::Completed)
        ->and(File::exists($source))->toBeFalse(); // <-- the bug: this used to stay forever
});

test('deletes the full source video when no other reels are pending', function () {
    $match = FootballMatch::factory()->completed()->create();
    $reel = MatchReel::factory()->create(['match_id' => $match->id, 'status' => ReelStatus::Completed]);

    $source = $this->tempDir."/full-{$match->ulid}.mp4";
    $output = $this->tempDir.'/'.$reel->ulid.'.mp4';
    File::put($source, str_repeat('x', 1024));
    File::put($output, 'clip');

    runCleanup(new GenerateMatchReel($reel), $output, $source, $match);

    expect(File::exists($source))->toBeFalse()
        ->and(File::exists($output))->toBeFalse();
});

test('keeps the full source video while other reels are still pending', function () {
    $match = FootballMatch::factory()->completed()->create();
    $reel = MatchReel::factory()->create(['match_id' => $match->id, 'status' => ReelStatus::Completed]);
    MatchReel::factory()->create(['match_id' => $match->id, 'status' => ReelStatus::Pending]);

    $source = $this->tempDir."/full-{$match->ulid}.mp4";
    $output = $this->tempDir.'/'.$reel->ulid.'.mp4';
    File::put($source, str_repeat('x', 1024));
    File::put($output, 'clip');

    runCleanup(new GenerateMatchReel($reel), $output, $source, $match);

    // Output clip always removed, but the shared source stays for the pending reel.
    expect(File::exists($output))->toBeFalse()
        ->and(File::exists($source))->toBeTrue();
});
