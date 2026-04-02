<?php

use App\Enums\VideoUploadStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('can check video upload status', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('clubs.matches.videoUpload.show', [$club, $match]))
        ->assertOk()
        ->assertJsonPath('video_upload.status', 'ready');
});

test('show returns null when no video upload exists', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->getJson(route('clubs.matches.videoUpload.show', [$club, $match]))
        ->assertOk()
        ->assertJsonPath('video_upload', null);
});

test('admin can delete video upload', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $videoUpload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'uploads/test/video.mp4',
    ]);

    Storage::disk('s3')->put('uploads/test/video.mp4', 'fake');

    $this->actingAs($user)
        ->deleteJson(route('clubs.matches.videoUpload.destroy', [$club, $match]))
        ->assertOk();

    $this->assertDatabaseMissing('match_video_uploads', ['id' => $videoUpload->id]);
    Storage::disk('s3')->assertMissing('uploads/test/video.mp4');
});

test('retry youtube requires super admin', function () {
    Queue::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'videos/test/1080p.mp4',
    ]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.videoUpload.retryYouTube', [$club, $match]))
        ->assertForbidden();
});

test('super admin can retry youtube without changing status', function () {
    Queue::fake();

    $superAdmin = User::factory()->create();
    config(['app.super_admin_emails' => [$superAdmin->email]]);

    $club = Club::factory()->create();
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $superAdmin->id,
        's3_path' => 'videos/test/1080p.mp4',
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('clubs.matches.videoUpload.retryYouTube', [$club, $match]))
        ->assertOk();

    $this->assertDatabaseHas('match_video_uploads', [
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Ready->value,
        'error_message' => null,
    ]);
});

test('delete returns 404 when no video exists', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->deleteJson(route('clubs.matches.videoUpload.destroy', [$club, $match]))
        ->assertNotFound();
});
