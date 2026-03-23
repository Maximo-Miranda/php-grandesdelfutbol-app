<?php

use App\Jobs\ProcessEncodedVideo;
use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use App\Services\ReelService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('skips when video upload is not ready', function () {
    $upload = MatchVideoUpload::factory()->encoding()->create();

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldNotReceive('downloadVideo');

    $reelMock = mock(ReelService::class);
    $reelMock->shouldNotReceive('generateReelsForMatch');

    (new ProcessEncodedVideo($upload))->handle($bunnyMock, $reelMock);
});

it('downloads 720p to s3 and dispatches batch', function () {
    Bus::fake([UploadMatchToYouTube::class]);
    Storage::fake('s3');

    $upload = MatchVideoUpload::factory()->ready()->create([
        's3_path' => null,
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldReceive('downloadVideo')
        ->once()
        ->andReturnUsing(function ($videoId, $outputPath, $resolution) {
            file_put_contents($outputPath, 'fake-720p');
        });

    $reelMock = mock(ReelService::class);

    (new ProcessEncodedVideo($upload))->handle($bunnyMock, $reelMock);

    $upload->refresh();

    expect($upload->s3_path)->toBe("match-videos/{$upload->match->ulid}.mp4");
});

it('skips s3 download when s3_path already set', function () {
    Bus::fake();

    $upload = MatchVideoUpload::factory()->ready()->create([
        's3_path' => 'match-videos/existing.mp4',
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldNotReceive('downloadVideo');

    $reelMock = mock(ReelService::class);

    (new ProcessEncodedVideo($upload))->handle($bunnyMock, $reelMock);
});

it('is dispatched on video-processing queue', function () {
    $upload = MatchVideoUpload::factory()->create();

    $job = new ProcessEncodedVideo($upload);

    expect($job->queue)->toBe('video-processing');
});
