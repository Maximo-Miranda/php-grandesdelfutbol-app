<?php

use App\Services\BunnyStreamService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'bunny.stream_library_id' => 'test-lib-123',
        'bunny.stream_api_key' => 'test-api-key',
        'bunny.cdn_hostname' => 'vz-test.b-cdn.net',
    ]);
});

test('createVideo sends POST to bunny API', function () {
    Http::fake([
        'video.bunnycdn.com/library/test-lib-123/videos' => Http::response([
            'guid' => 'video-guid-abc',
        ], 200),
    ]);

    $service = new BunnyStreamService;
    $result = $service->createVideo('Test Video');

    expect($result['guid'])->toBe('video-guid-abc');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://video.bunnycdn.com/library/test-lib-123/videos'
            && $request->method() === 'POST'
            && $request->header('AccessKey')[0] === 'test-api-key';
    });
});

test('getVideo retrieves video details', function () {
    Http::fake([
        'video.bunnycdn.com/library/test-lib-123/videos/video-123' => Http::response([
            'guid' => 'video-123',
            'status' => 3,
            'length' => 3600,
        ], 200),
    ]);

    $service = new BunnyStreamService;
    $result = $service->getVideo('video-123');

    expect($result['guid'])->toBe('video-123')
        ->and($result['length'])->toBe(3600);
});

test('deleteVideo sends DELETE request', function () {
    Http::fake([
        'video.bunnycdn.com/library/test-lib-123/videos/video-123' => Http::response(null, 200),
    ]);

    $service = new BunnyStreamService;
    $service->deleteVideo('video-123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'video-123')
            && $request->method() === 'DELETE';
    });
});

test('getTusUploadUrl generates signed credentials', function () {
    $service = new BunnyStreamService;
    $result = $service->getTusUploadUrl('video-123');

    expect($result['upload_url'])->toBe('https://video.bunnycdn.com/tusupload')
        ->and($result['video_id'])->toBe('video-123')
        ->and($result['library_id'])->toBe('test-lib-123')
        ->and($result['auth_signature'])->toBeString()->not->toBeEmpty()
        ->and($result['auth_expire'])->toBeInt()->toBeGreaterThan(time());
});

test('downloadVideo throws on failure', function () {
    $service = new BunnyStreamService;
    $tempFile = tempnam(sys_get_temp_dir(), 'bunny-test-');

    expect(fn () => $service->downloadVideo('nonexistent-video', $tempFile))
        ->toThrow(\RuntimeException::class);

    @unlink($tempFile);
});

test('getStreamUrl returns HLS URL', function () {
    $service = new BunnyStreamService;
    $url = $service->getStreamUrl('video-123');

    expect($url)->toBe('https://vz-test.b-cdn.net/video-123/playlist.m3u8');
});
