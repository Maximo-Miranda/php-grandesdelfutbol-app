<?php

use App\Enums\VideoUploadStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\GoogleDriveService;
use App\Services\YouTubeService;

use function Pest\Laravel\mock;

// ── Embed URL cascading ──────────────────────────────────────────

test('embed_url returns youtube when both youtube and drive are available', function () {
    $upload = MatchVideoUpload::factory()->ready()->create([
        'youtube_video_id' => 'abc123',
        'drive_file_id' => 'drive-id',
        'drive_shared_at' => now(),
    ]);

    expect($upload->embed_url)->toBe('https://www.youtube.com/embed/abc123');
});

test('embed_url returns drive when youtube is not available', function () {
    $upload = MatchVideoUpload::factory()->ready()->create([
        'youtube_video_id' => null,
        'drive_file_id' => 'drive-id-456',
        'drive_shared_at' => now(),
    ]);

    expect($upload->embed_url)->toBe('https://drive.google.com/file/d/drive-id-456/preview');
});

test('embed_url returns null when drive is not shared', function () {
    $upload = MatchVideoUpload::factory()->ready()->create([
        'youtube_video_id' => null,
        'drive_file_id' => 'drive-id',
        'drive_shared_at' => null,
    ]);

    expect($upload->embed_url)->toBeNull();
});

test('embed_url returns null when nothing is available', function () {
    $upload = MatchVideoUpload::factory()->ready()->create([
        'youtube_video_id' => null,
        'drive_file_id' => null,
    ]);

    expect($upload->embed_url)->toBeNull();
});

// ── Delete handler — cleans all storage ──────────────────────────

test('delete video cleans youtube, drive, and s3', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/test/720p.mp4', 'fake');
    Storage::disk('s3')->put('videos/test/reels.mp4', 'fake');

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $upload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        'youtube_video_id' => 'yt-123',
        'drive_file_id' => 'drive-original',
        'drive_reels_file_id' => 'drive-720p',
        's3_path' => 'videos/test/720p.mp4',
        's3_reels_path' => 'videos/test/reels.mp4',
    ]);

    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('deleteFile')->with('drive-original')->once();
    $driveMock->shouldReceive('deleteFile')->with('drive-720p')->once();

    $ytMock = mock(YouTubeService::class);
    $ytMock->shouldReceive('deleteVideo')->with('yt-123')->once();

    $this->actingAs($user)
        ->deleteJson(route('clubs.matches.videoUpload.destroy', [$club, $match]))
        ->assertOk();

    $this->assertDatabaseMissing('match_video_uploads', ['id' => $upload->id]);
    Storage::disk('s3')->assertMissing('videos/test/720p.mp4');
    Storage::disk('s3')->assertMissing('videos/test/reels.mp4');
});

// ── Init upload replaces stale uploading records ─────────────────

test('init drive upload replaces stale uploading record', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $stale = MatchVideoUpload::factory()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        'status' => VideoUploadStatus::Uploading,
    ]);

    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('ensureClubFolder')->andReturn('folder-id');
    $driveMock->shouldReceive('createResumableSession')->andReturn('https://googleapis.com/session');

    mock(GoogleAuthService::class)
        ->shouldReceive('getAccessToken')
        ->andReturn(['access_token' => 'token', 'expires_at' => time() + 3600]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.driveUpload.init', [$club, $match]), [
            'filename' => 'video.mp4',
            'filesize' => 1000000,
            'content_type' => 'video/mp4',
        ])
        ->assertOk();

    $this->assertDatabaseMissing('match_video_uploads', ['id' => $stale->id]);
    $this->assertDatabaseCount('match_video_uploads', 1);
});

test('init drive upload blocks when ready video exists', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.driveUpload.init', [$club, $match]), [
            'filename' => 'video.mp4',
            'filesize' => 1000000,
            'content_type' => 'video/mp4',
        ])
        ->assertStatus(422);
});

// ── Probe endpoint ───────────────────────────────────────────────

test('probe rejects non-googleapis session_uri', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.driveUpload.probe', [$club, $match]), [
            'session_uri' => 'https://evil.com/upload',
            'total_size' => 1000,
        ])
        ->assertStatus(422);
});
