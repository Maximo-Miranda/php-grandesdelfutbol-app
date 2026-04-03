<?php

use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('cleanup skips videos without youtube', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/test/reels.mp4', 'fake');

    $upload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => FootballMatch::factory()->completed()->create()->id,
        'uploaded_by' => User::factory()->create()->id,
        'youtube_video_id' => null,
        'drive_reels_file_id' => 'drive-720p',
        's3_reels_path' => 'videos/test/reels.mp4',
        's3_reels_uploaded_at' => now()->subDays(31),
        's3_path' => 'videos/test/720p.mp4',
    ]);

    $this->artisan('app:cleanup-match-videos --days=30')
        ->assertSuccessful()
        ->expectsOutputToContain('no tiene video en YouTube');

    $this->assertDatabaseHas('match_video_uploads', [
        'id' => $upload->id,
        's3_path' => 'videos/test/720p.mp4',
        's3_reels_path' => 'videos/test/reels.mp4',
    ]);
});

test('cleanup deletes s3 files when youtube is available', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/test/reels.mp4', 'fake');

    $upload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => FootballMatch::factory()->completed()->create()->id,
        'uploaded_by' => User::factory()->create()->id,
        'youtube_video_id' => 'yt-abc123',
        'drive_reels_file_id' => 'drive-720p',
        's3_reels_path' => 'videos/test/reels.mp4',
        's3_reels_uploaded_at' => now()->subDays(31),
        's3_path' => 'videos/test/720p.mp4',
    ]);

    $this->artisan('app:cleanup-match-videos --days=30')
        ->assertSuccessful();

    $this->assertDatabaseHas('match_video_uploads', [
        'id' => $upload->id,
        's3_path' => null,
        's3_reels_path' => null,
    ]);

    Storage::disk('s3')->assertMissing('videos/test/reels.mp4');
});

test('cleanup skips videos without drive reels backup', function () {
    $upload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => FootballMatch::factory()->completed()->create()->id,
        'uploaded_by' => User::factory()->create()->id,
        'youtube_video_id' => 'yt-abc123',
        'drive_reels_file_id' => null,
        's3_reels_path' => 'videos/test/reels.mp4',
        's3_reels_uploaded_at' => now()->subDays(31),
    ]);

    $this->artisan('app:cleanup-match-videos --days=30')
        ->assertSuccessful()
        ->expectsOutputToContain('no tiene 720p en Drive');

    $this->assertDatabaseHas('match_video_uploads', [
        'id' => $upload->id,
        's3_reels_path' => 'videos/test/reels.mp4',
    ]);
});
