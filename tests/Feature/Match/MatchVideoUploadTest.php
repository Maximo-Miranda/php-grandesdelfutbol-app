<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('admin can register video upload after S3 upload', function () {
    Queue::fake([ProcessUploadedVideo::class]);

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.videoUpload.store', [$club, $match]), [
            'filename' => 'match-video.mp4',
            'filesize' => 5000000000,
            's3_key' => 'uploads/01ABC123/match-video.mp4',
        ])
        ->assertOk()
        ->assertJsonStructure(['video_upload']);

    $this->assertDatabaseHas('match_video_uploads', [
        'football_match_id' => $match->id,
        'status' => VideoUploadStatus::Encoding->value,
        's3_path' => 'uploads/01ABC123/match-video.mp4',
    ]);

    Queue::assertPushed(ProcessUploadedVideo::class);
});

test('cannot upload to match that already has video', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.videoUpload.store', [$club, $match]), [
            'filename' => 'match-video.mp4',
            'filesize' => 5000000000,
            's3_key' => 'uploads/test/file.mp4',
        ])
        ->assertStatus(422);
});

test('non-admin cannot initiate video upload', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.videoUpload.store', [$club, $match]), [
            'filename' => 'match-video.mp4',
            'filesize' => 5000000000,
            's3_key' => 'uploads/test/file.mp4',
        ])
        ->assertForbidden();
});

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

test('delete returns 404 when no video exists', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->deleteJson(route('clubs.matches.videoUpload.destroy', [$club, $match]))
        ->assertNotFound();
});
