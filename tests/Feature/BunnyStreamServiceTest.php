<?php

use App\Models\MatchVideoUpload;

it('model embed_url returns youtube when youtube_video_id set', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => 'yt-abc123',
    ]);

    expect($upload->embed_url)->toBe('https://www.youtube.com/embed/yt-abc123');
});

it('model embed_url returns null when no youtube', function () {
    $upload = MatchVideoUpload::factory()->create([
        'youtube_video_id' => null,
    ]);

    expect($upload->embed_url)->toBeNull();
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
