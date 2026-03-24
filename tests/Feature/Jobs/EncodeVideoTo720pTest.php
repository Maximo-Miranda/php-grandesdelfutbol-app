<?php

use App\Jobs\EncodeVideoTo720p;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function fakeFfprobeJson(int $height = 1080, string $videoCodec = 'h264', string $audioCodec = 'aac', float $duration = 5400.5): string
{
    return json_encode([
        'streams' => [
            ['codec_type' => 'video', 'codec_name' => $videoCodec, 'height' => $height],
            ['codec_type' => 'audio', 'codec_name' => $audioCodec],
        ],
        'format' => ['duration' => (string) $duration],
    ]);
}

function setupEncodeTest(string $ffprobeJson): array
{
    Storage::fake('s3');

    $videoUpload = MatchVideoUpload::factory()->encoding()->create([
        's3_path' => 'videos/matches/test-ulid/original.mp4',
    ]);

    Storage::disk('s3')->put($videoUpload->s3_path, 'fake-video-content');

    $match = $videoUpload->match;
    $tempDir = storage_path('app/temp/encoding');
    File::ensureDirectoryExists($tempDir);

    $outputFile = $tempDir."/720p-{$match->ulid}.mp4";

    Process::fake([
        '*' => Process::sequence([
            Process::result(output: $ffprobeJson),
            Process::result(),
        ]),
    ]);

    // Pre-create the output file that ffmpeg would produce
    file_put_contents($outputFile, 'encoded-content');

    return [$videoUpload, new EncodeVideoTo720p($videoUpload), $outputFile];
}

afterEach(function () {
    $tempDir = storage_path('app/temp/encoding');

    if (File::isDirectory($tempDir)) {
        File::deleteDirectory($tempDir);
    }
});

test('stream copies when input is 720p h264 with aac audio', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 720, videoCodec: 'h264', audioCodec: 'aac')
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'copy'
            && in_array('-c:a', $cmd)
            && $cmd[array_search('-c:a', $cmd) + 1] === 'copy'
            && ! in_array('scale=-2:720', $cmd);
    });
});

test('stream copies when input is below 720p h264', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 480, videoCodec: 'h264', audioCodec: 'aac')
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'copy';
    });
});

test('re-encodes audio when stream copying non-aac input', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 720, videoCodec: 'h264', audioCodec: 'mp3')
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'copy'
            && in_array('-c:a', $cmd)
            && $cmd[array_search('-c:a', $cmd) + 1] === 'aac'
            && in_array('128k', $cmd);
    });
});

test('full transcodes when input is above 720p', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 1080, videoCodec: 'h264', audioCodec: 'aac')
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'libx264'
            && in_array('-preset', $cmd)
            && $cmd[array_search('-preset', $cmd) + 1] === 'veryfast'
            && in_array('-crf', $cmd)
            && $cmd[array_search('-crf', $cmd) + 1] === '23'
            && in_array('scale=-2:720', $cmd)
            && in_array('-c:a', $cmd)
            && $cmd[array_search('-c:a', $cmd) + 1] === 'copy';
    });
});

test('full transcodes with audio re-encode for non-aac 1080p', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 1080, videoCodec: 'h264', audioCodec: 'opus')
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'libx264'
            && in_array('-c:a', $cmd)
            && $cmd[array_search('-c:a', $cmd) + 1] === 'aac'
            && in_array('128k', $cmd);
    });
});

test('full transcodes for non-h264 720p input', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 720, videoCodec: 'hevc', audioCodec: 'aac')
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'libx264'
            && in_array('scale=-2:720', $cmd);
    });
});

test('falls back to full transcode when ffprobe fails', function () {
    Storage::fake('s3');

    $videoUpload = MatchVideoUpload::factory()->encoding()->create([
        's3_path' => 'videos/matches/test-ulid/original.mp4',
    ]);

    Storage::disk('s3')->put($videoUpload->s3_path, 'fake-video-content');

    $match = $videoUpload->match;
    $tempDir = storage_path('app/temp/encoding');
    File::ensureDirectoryExists($tempDir);
    $outputFile = $tempDir."/720p-{$match->ulid}.mp4";

    Process::fake([
        '*' => Process::sequence([
            Process::result(exitCode: 1, errorOutput: 'ffprobe error'),
            Process::result(),
        ]),
    ]);

    file_put_contents($outputFile, 'encoded-content');

    $job = new EncodeVideoTo720p($videoUpload);
    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-c:v', $cmd)
            && $cmd[array_search('-c:v', $cmd) + 1] === 'libx264'
            && in_array('-preset', $cmd)
            && $cmd[array_search('-preset', $cmd) + 1] === 'veryfast'
            && in_array('scale=-2:720', $cmd);
    });
});

test('updates duration seconds from ffprobe', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 1080, duration: 3600.7)
    );

    $job->handle();

    expect($videoUpload->fresh()->duration_seconds)->toBe(3601);
});

test('does not execute when batch is cancelled', function () {
    Storage::fake('s3');
    Process::fake();

    $videoUpload = MatchVideoUpload::factory()->encoding()->create([
        's3_path' => 'videos/matches/test-ulid/original.mp4',
    ]);

    $batch = Bus::batch([])->allowFailures()->dispatch();
    $batch->cancel();

    $job = new EncodeVideoTo720p($videoUpload);
    $job->withBatchId($batch->id);

    $job->handle();

    Process::assertNothingRan();
});

test('uses threads 0 for auto-threading in transcode path', function () {
    [$videoUpload, $job] = setupEncodeTest(
        fakeFfprobeJson(height: 1080)
    );

    $job->handle();

    Process::assertRan(function ($process) {
        $cmd = $process->command;

        return in_array('ffmpeg', $cmd)
            && in_array('-threads', $cmd)
            && $cmd[array_search('-threads', $cmd) + 1] === '0';
    });
});
