<?php

use App\Jobs\DeleteBunnyStreamVideo;
use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;

use function Pest\Laravel\mock;

it('deletes video from bunny and marks bunny_deleted_at', function () {
    $upload = MatchVideoUpload::factory()->create([
        'bunny_deleted_at' => null,
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldReceive('deleteVideo')
        ->with($upload->bunny_video_id)
        ->once();

    (new DeleteBunnyStreamVideo($upload))->handle($bunnyMock);

    expect($upload->fresh()->bunny_deleted_at)->not->toBeNull();
});

it('skips deletion when bunny already deleted', function () {
    $upload = MatchVideoUpload::factory()->create([
        'bunny_deleted_at' => now(),
    ]);

    $bunnyMock = mock(BunnyStreamService::class);
    $bunnyMock->shouldNotReceive('deleteVideo');

    (new DeleteBunnyStreamVideo($upload))->handle($bunnyMock);
});

it('is dispatched on default queue', function () {
    $upload = MatchVideoUpload::factory()->create();

    $job = new DeleteBunnyStreamVideo($upload);

    expect($job->queue)->toBe('default');
});
