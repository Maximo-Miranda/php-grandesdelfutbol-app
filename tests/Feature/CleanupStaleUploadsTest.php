<?php

use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;

test('cleanup deletes uploads in uploading status older than 7 days', function () {
    $user = User::factory()->create();
    $match = FootballMatch::factory()->completed()->create();

    $stale = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        'status' => VideoUploadStatus::Uploading,
        'created_at' => now()->subDays(8),
    ]);

    $this->artisan('app:cleanup-stale-uploads --days=7')
        ->assertSuccessful()
        ->expectsOutputToContain('1 uploads huerfanos eliminados');

    $this->assertDatabaseMissing('match_video_uploads', ['id' => $stale->id]);
});

test('cleanup does not delete recent uploading records', function () {
    $user = User::factory()->create();
    $match = FootballMatch::factory()->completed()->create();

    $recent = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        'status' => VideoUploadStatus::Uploading,
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('app:cleanup-stale-uploads --days=7')
        ->assertSuccessful();

    $this->assertDatabaseHas('match_video_uploads', ['id' => $recent->id]);
});

test('cleanup does not delete uploads in other statuses', function () {
    $user = User::factory()->create();
    $match = FootballMatch::factory()->completed()->create();

    $ready = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        'created_at' => now()->subDays(30),
    ]);

    $encoding = MatchVideoUpload::factory()->create([
        'football_match_id' => FootballMatch::factory()->completed()->create()->id,
        'uploaded_by' => $user->id,
        'status' => VideoUploadStatus::Encoding,
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('app:cleanup-stale-uploads --days=7')
        ->assertSuccessful();

    $this->assertDatabaseHas('match_video_uploads', ['id' => $ready->id]);
    $this->assertDatabaseHas('match_video_uploads', ['id' => $encoding->id]);
});

test('cleanup reports no orphans when none exist', function () {
    $this->artisan('app:cleanup-stale-uploads --days=7')
        ->assertSuccessful()
        ->expectsOutputToContain('No hay uploads huerfanos');
});
