<?php

use App\Jobs\GenerateMatchReel;
use App\Models\MatchReel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->tempDir = storage_path('app/temp/reels-test');
    File::ensureDirectoryExists($this->tempDir);
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

test('cutSegment includes watermark overlay when enabled and file exists', function () {
    Process::fake();

    $watermarkPath = $this->tempDir.'/watermark.png';
    file_put_contents($watermarkPath, 'fake-watermark');

    config([
        'reels.watermark_enabled' => true,
        'reels.watermark_path' => $watermarkPath,
        'reels.watermark_opacity' => 0.7,
        'reels.watermark_padding' => 20,
    ]);

    $reel = MatchReel::factory()->create(['start_second' => 10, 'end_second' => 35]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'cutSegment');
    $method->invoke($job, $this->tempDir.'/source.mp4', $this->tempDir.'/output.mp4');

    Process::assertRan(function ($process) use ($watermarkPath) {
        $command = $process->command;

        return in_array('ffmpeg', $command)
            && in_array($watermarkPath, $command)
            && in_array('-filter_complex', $command);
    });
});

test('cutSegment skips watermark when disabled', function () {
    Process::fake();

    config([
        'reels.watermark_enabled' => false,
        'reels.watermark_path' => '/nonexistent/watermark.png',
    ]);

    $reel = MatchReel::factory()->create(['start_second' => 10, 'end_second' => 35]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'cutSegment');
    $method->invoke($job, '/tmp/source.mp4', '/tmp/output.mp4');

    Process::assertRan(function ($process) {
        $command = $process->command;

        return in_array('ffmpeg', $command)
            && ! in_array('-filter_complex', $command);
    });
});

test('cutSegment skips watermark when file does not exist', function () {
    Process::fake();

    config([
        'reels.watermark_enabled' => true,
        'reels.watermark_path' => '/nonexistent/watermark.png',
    ]);

    $reel = MatchReel::factory()->create(['start_second' => 10, 'end_second' => 35]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'cutSegment');
    $method->invoke($job, '/tmp/source.mp4', '/tmp/output.mp4');

    Process::assertRan(function ($process) {
        $command = $process->command;

        return in_array('ffmpeg', $command)
            && ! in_array('-filter_complex', $command);
    });
});

test('cutSegment applies correct opacity and padding from config', function () {
    Process::fake();

    $watermarkPath = $this->tempDir.'/watermark.png';
    file_put_contents($watermarkPath, 'fake-watermark');

    config([
        'reels.watermark_enabled' => true,
        'reels.watermark_path' => $watermarkPath,
        'reels.watermark_opacity' => 0.5,
        'reels.watermark_padding' => 30,
    ]);

    $reel = MatchReel::factory()->create(['start_second' => 0, 'end_second' => 25]);
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'cutSegment');
    $method->invoke($job, '/tmp/source.mp4', '/tmp/output.mp4');

    Process::assertRan(function ($process) {
        $command = $process->command;
        $filterIdx = array_search('-filter_complex', $command);

        return $filterIdx !== false
            && str_contains($command[$filterIdx + 1], 'aa=0.5')
            && str_contains($command[$filterIdx + 1], 'overlay=W-w-30:30');
    });
});

test('storeOutputAndComplete throws when output file is too small', function () {
    $smallFile = $this->tempDir.'/tiny.mp4';
    file_put_contents($smallFile, str_repeat('x', 100));

    $reel = MatchReel::factory()->create();
    $job = new GenerateMatchReel($reel);

    $method = new ReflectionMethod($job, 'storeOutputAndComplete');

    expect(fn () => $method->invoke($job, $smallFile))
        ->toThrow(RuntimeException::class, 'too small');
});
