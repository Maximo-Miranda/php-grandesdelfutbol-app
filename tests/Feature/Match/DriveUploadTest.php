<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $this->club->id, 'user_id' => $this->user->id]);
    $this->match = FootballMatch::factory()->completed()->create(['club_id' => $this->club->id]);
});

test('admin can initiate drive upload', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('ensureClubFolder')
        ->once()
        ->andReturn('folder-id-123');
    $driveMock->shouldReceive('createResumableSession')
        ->once()
        ->andReturn('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&upload_id=xa298sd');

    mock(GoogleAuthService::class)
        ->shouldReceive('getAccessToken')
        ->once()
        ->andReturn(['access_token' => 'ya29.test-token', 'expires_at' => time() + 3600]);

    $this->actingAs($this->user)
        ->postJson(route('clubs.matches.driveUpload.init', [$this->club, $this->match]), [
            'filename' => 'partido.mp4',
            'filesize' => 5000000000,
            'content_type' => 'video/mp4',
        ])
        ->assertOk()
        ->assertJsonStructure(['session_uri', 'access_token', 'expires_at', 'upload_ulid']);

    $this->assertDatabaseHas('match_video_uploads', [
        'football_match_id' => $this->match->id,
        'status' => VideoUploadStatus::Uploading->value,
        'original_filename' => 'partido.mp4',
    ]);
});

test('cannot init drive upload to match that already has video', function () {
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $this->match->id,
        'uploaded_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('clubs.matches.driveUpload.init', [$this->club, $this->match]), [
            'filename' => 'partido.mp4',
            'filesize' => 5000000000,
            'content_type' => 'video/mp4',
        ])
        ->assertStatus(422);
});

test('non-admin cannot initiate drive upload', function () {
    $regularUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $regularUser->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($regularUser)
        ->postJson(route('clubs.matches.driveUpload.init', [$club, $match]), [
            'filename' => 'partido.mp4',
            'filesize' => 5000000000,
            'content_type' => 'video/mp4',
        ])
        ->assertForbidden();
});

test('init drive upload validates required fields', function () {
    $this->actingAs($this->user)
        ->postJson(route('clubs.matches.driveUpload.init', [$this->club, $this->match]), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['filename', 'filesize', 'content_type']);
});

test('admin can complete drive upload', function () {
    Queue::fake([ProcessUploadedVideo::class]);

    $videoUpload = MatchVideoUpload::factory()->create([
        'football_match_id' => $this->match->id,
        'uploaded_by' => $this->user->id,
        'status' => VideoUploadStatus::Uploading,
    ]);

    mock(GoogleDriveService::class)
        ->shouldReceive('getFileMetadata')
        ->with('drive-file-id-abc')
        ->once()
        ->andReturn([
            'id' => 'drive-file-id-abc',
            'name' => 'test.mp4',
            'size' => 5000000000,
            'mimeType' => 'video/mp4',
        ]);

    $this->actingAs($this->user)
        ->postJson(route('clubs.matches.driveUpload.complete', [$this->club, $this->match]), [
            'drive_file_id' => 'drive-file-id-abc',
            'upload_ulid' => $videoUpload->ulid,
        ])
        ->assertOk()
        ->assertJsonStructure(['video_upload']);

    $this->assertDatabaseHas('match_video_uploads', [
        'id' => $videoUpload->id,
        'drive_file_id' => 'drive-file-id-abc',
        'status' => VideoUploadStatus::Encoding->value,
    ]);

    Queue::assertPushed(ProcessUploadedVideo::class);
});

test('complete requires matching upload ulid', function () {
    $videoUpload = MatchVideoUpload::factory()->create([
        'football_match_id' => $this->match->id,
        'uploaded_by' => $this->user->id,
        'status' => VideoUploadStatus::Uploading,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('clubs.matches.driveUpload.complete', [$this->club, $this->match]), [
            'drive_file_id' => 'drive-file-id-abc',
            'upload_ulid' => 'wrong-ulid-value',
        ])
        ->assertNotFound();
});

test('admin can refresh drive upload token', function () {
    mock(GoogleAuthService::class)
        ->shouldReceive('getAccessToken')
        ->once()
        ->andReturn(['access_token' => 'ya29.fresh-token', 'expires_at' => time() + 3600]);

    $this->actingAs($this->user)
        ->postJson(route('clubs.driveUpload.refreshToken', [$this->club]))
        ->assertOk()
        ->assertJsonStructure(['access_token', 'expires_at']);
});

test('unauthenticated user cannot access drive upload endpoints', function () {
    $this->postJson(route('clubs.matches.driveUpload.init', [$this->club, $this->match]), [
        'filename' => 'partido.mp4',
        'filesize' => 5000000000,
        'content_type' => 'video/mp4',
    ])->assertUnauthorized();

    $this->postJson(route('clubs.driveUpload.refreshToken', [$this->club]))
        ->assertUnauthorized();

    $this->postJson(route('clubs.matches.driveUpload.complete', [$this->club, $this->match]), [
        'drive_file_id' => 'abc',
        'upload_ulid' => 'xyz',
    ])->assertUnauthorized();
});
