<?php

use App\Enums\ReelStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
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
