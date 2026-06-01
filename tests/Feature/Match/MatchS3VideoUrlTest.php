<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('summary passes s3VideoUrl when video is ready and youtube is not available', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/encoded/test.mp4', 'fake-video-content');

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'videos/encoded/test.mp4',
        'best_resolution' => '720p',
        'youtube_video_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('s3VideoUrl', fn ($value) => str_contains($value, 'videos/encoded/test.mp4'))
        );
});

test('summary passes null s3VideoUrl when youtube is available', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'videos/encoded/test.mp4',
        'best_resolution' => '720p',
        'youtube_video_id' => 'abc123',
    ]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('s3VideoUrl', null)
        );
});

test('summary passes null s3VideoUrl when no video upload exists', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('s3VideoUrl', null)
        );
});

test('summary passes null s3VideoUrl when video has no best_resolution', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->encoding()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'videos/encoded/test.mp4',
        'best_resolution' => null,
        'youtube_video_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('s3VideoUrl', null)
        );
});

test('public match page passes s3VideoUrl when video is ready and youtube is not available', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/encoded/public-test.mp4', 'fake-video-content');

    $match = FootballMatch::factory()->completed()->create(['share_token' => 's3-video-token']);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        's3_path' => 'videos/encoded/public-test.mp4',
        'best_resolution' => '720p',
        'youtube_video_id' => null,
    ]);

    $this->get(route('match.public', 's3-video-token'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matches/Public')
            ->where('s3VideoUrl', fn ($value) => str_contains($value, 'videos/encoded/public-test.mp4'))
        );
});

test('public match page passes null s3VideoUrl when youtube is available', function () {
    $match = FootballMatch::factory()->completed()->create(['share_token' => 'yt-token']);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        's3_path' => 'videos/encoded/test.mp4',
        'best_resolution' => '720p',
        'youtube_video_id' => 'yt-abc123',
    ]);

    $this->get(route('match.public', 'yt-token'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matches/Public')
            ->where('s3VideoUrl', null)
        );
});

test('summary exposes a lightweight videoStatus prop for background polling', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'videos/encoded/test.mp4',
        'best_resolution' => '720p',
        'youtube_video_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('videoStatus.status', 'ready')
            ->where('videoStatus.on_youtube', false)
        );
});

test('videoStatus marks on_youtube once the video has a youtube id', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
        's3_path' => 'videos/encoded/test.mp4',
        'best_resolution' => '720p',
        'youtube_video_id' => 'abc123',
    ]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->where('videoStatus.on_youtube', true)
        );
});
