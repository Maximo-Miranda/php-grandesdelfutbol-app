<?php

use App\Enums\VideoUploadStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use App\Services\BunnyStreamService;

test('admin can initiate video upload', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $mock = Mockery::mock(BunnyStreamService::class);
    $mock->shouldReceive('createVideo')
        ->once()
        ->andReturn(['guid' => 'bunny-video-123']);
    $mock->shouldReceive('getTusUploadUrl')
        ->once()
        ->andReturn([
            'upload_url' => 'https://video.bunnycdn.com/tusupload',
            'video_id' => 'bunny-video-123',
            'auth_signature' => 'test-signature',
            'auth_expire' => time() + 86400,
            'library_id' => '123456',
        ]);
    app()->instance(BunnyStreamService::class, $mock);

    $this->actingAs($user)
        ->postJson(route('clubs.matches.videoUpload.store', [$club, $match]), [
            'filename' => 'match-video.mp4',
            'filesize' => 5000000000,
        ])
        ->assertOk()
        ->assertJsonStructure(['video_upload', 'upload_url', 'auth_signature', 'library_id', 'video_id']);

    $this->assertDatabaseHas('match_video_uploads', [
        'football_match_id' => $match->id,
        'bunny_video_id' => 'bunny-video-123',
        'status' => VideoUploadStatus::Uploading->value,
    ]);
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
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $videoUpload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
    ]);

    $mock = Mockery::mock(BunnyStreamService::class);
    $mock->shouldReceive('deleteVideo')->once();
    app()->instance(BunnyStreamService::class, $mock);

    $this->actingAs($user)
        ->deleteJson(route('clubs.matches.videoUpload.destroy', [$club, $match]))
        ->assertOk();

    $this->assertDatabaseMissing('match_video_uploads', ['id' => $videoUpload->id]);
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
