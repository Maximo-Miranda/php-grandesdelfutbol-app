<?php

use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use Illuminate\Support\Facades\Http;

it('detects fully encoded video', function () {
    Http::fake([
        'video.bunnycdn.com/*' => Http::response([
            'guid' => 'test-id',
            'encodeProgress' => 100,
        ]),
    ]);

    $service = new BunnyStreamService;

    expect($service->isFullyEncoded('test-id'))->toBeTrue();
});

it('detects not fully encoded video', function () {
    Http::fake([
        'video.bunnycdn.com/*' => Http::response([
            'guid' => 'test-id',
            'encodeProgress' => 75,
        ]),
    ]);

    $service = new BunnyStreamService;

    expect($service->isFullyEncoded('test-id'))->toBeFalse();
});

it('parses available resolutions sorted highest to lowest', function () {
    Http::fake([
        'video.bunnycdn.com/*' => Http::response([
            'guid' => 'test-id',
            'availableResolutions' => '240p,360p,480p,720p,1080p',
        ]),
    ]);

    $service = new BunnyStreamService;

    expect($service->getAvailableResolutions('test-id'))
        ->toBe(['1080p', '720p', '480p', '360p', '240p']);
});

it('returns empty array when no resolutions available', function () {
    Http::fake([
        'video.bunnycdn.com/*' => Http::response([
            'guid' => 'test-id',
            'availableResolutions' => '',
        ]),
    ]);

    $service = new BunnyStreamService;

    expect($service->getAvailableResolutions('test-id'))->toBe([]);
});

it('model embed_url prefers youtube when youtube_video_id set', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'yt-abc123',
    ]);

    expect($upload->embed_url)->toBe('https://www.youtube.com/embed/yt-abc123');
});

it('model embed_url falls back to bunny when no youtube', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
    ]);

    expect($upload->embed_url)->toContain('iframe.mediadelivery.net');
});

it('model embed_url returns null when bunny deleted and no youtube', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
        'bunny_deleted_at' => now(),
    ]);

    expect($upload->embed_url)->toBeNull();
});

it('model stream_url returns null when bunny deleted', function () {
    $upload = MatchVideoUpload::factory()->create([
        'bunny_deleted_at' => now(),
    ]);

    expect($upload->stream_url)->toBeNull();
});

it('model youtube_url returns correct url', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'dQw4w9WgXcQ',
    ]);

    expect($upload->youtube_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($upload->youtube_embed_url)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});

it('model youtube_url returns null when no youtube_video_id', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
    ]);

    expect($upload->youtube_url)->toBeNull()
        ->and($upload->youtube_embed_url)->toBeNull();
});
