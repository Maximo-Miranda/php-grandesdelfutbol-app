<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    foreach (['drive', 'youtube', 'reels'] as $dir) {
        File::ensureDirectoryExists(storage_path("app/temp/{$dir}"));
    }
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/temp/drive'));
    File::deleteDirectory(storage_path('app/temp/youtube'));
    File::deleteDirectory(storage_path('app/temp/reels'));
});

test('deletes stale temp video files and keeps recent ones', function () {
    $stale = storage_path('app/temp/reels/full-old.mp4');
    $recent = storage_path('app/temp/youtube/uploading.mov');

    File::put($stale, 'old');
    File::put($recent, 'fresh');
    touch($stale, now()->subHours(10)->getTimestamp());

    $this->artisan('videos:cleanup-temp --hours=6')->assertSuccessful();

    expect(File::exists($stale))->toBeFalse()
        ->and(File::exists($recent))->toBeTrue();
});

test('ignores non-video files', function () {
    $other = storage_path('app/temp/reels/notes.txt');
    File::put($other, 'keep');
    touch($other, now()->subHours(10)->getTimestamp());

    $this->artisan('videos:cleanup-temp --hours=6')->assertSuccessful();

    expect(File::exists($other))->toBeTrue();
});
