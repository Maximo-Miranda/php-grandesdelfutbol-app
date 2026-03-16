<?php

use App\Enums\AttendanceStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;
use App\Notifications\MatchVideoUploadedNotification;
use Illuminate\Support\Facades\Notification;

test('notifies confirmed attendees when youtube url is added', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $attendeeUser = User::factory()->withNtfy()->create();
    $player = Player::factory()->linked($attendeeUser)->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => AttendanceStatus::Confirmed,
    ]);

    $this->actingAs($admin)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toISOString(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => $match->max_players,
            'max_substitutes' => $match->max_substitutes,
            'registration_opens_hours' => $match->registration_opens_hours,
            'youtube_url' => 'https://www.youtube.com/watch?v=abc123',
        ])
        ->assertRedirect();

    Notification::assertSentTo($attendeeUser, MatchVideoUploadedNotification::class);
});

test('does not notify when youtube url already existed', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => 'https://www.youtube.com/watch?v=existing',
    ]);

    $attendeeUser = User::factory()->withNtfy()->create();
    $player = Player::factory()->linked($attendeeUser)->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => AttendanceStatus::Confirmed,
    ]);

    $this->actingAs($admin)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toISOString(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => $match->max_players,
            'max_substitutes' => $match->max_substitutes,
            'registration_opens_hours' => $match->registration_opens_hours,
            'youtube_url' => 'https://www.youtube.com/watch?v=newurl',
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('does not notify when update does not include youtube url', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => 'Updated Title',
            'scheduled_at' => $match->scheduled_at->toISOString(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => $match->max_players,
            'max_substitutes' => $match->max_substitutes,
            'registration_opens_hours' => $match->registration_opens_hours,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('does not notify declined attendees when youtube url is added', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $confirmedUser = User::factory()->withNtfy()->create();
    $confirmedPlayer = Player::factory()->linked($confirmedUser)->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $confirmedPlayer->id,
        'status' => AttendanceStatus::Confirmed,
    ]);

    $declinedUser = User::factory()->create();
    $declinedPlayer = Player::factory()->linked($declinedUser)->create(['club_id' => $club->id]);
    MatchAttendance::factory()->declined()->create([
        'match_id' => $match->id,
        'player_id' => $declinedPlayer->id,
    ]);

    $this->actingAs($admin)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toISOString(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => $match->max_players,
            'max_substitutes' => $match->max_substitutes,
            'registration_opens_hours' => $match->registration_opens_hours,
            'youtube_url' => 'https://www.youtube.com/watch?v=abc123',
        ])
        ->assertRedirect();

    Notification::assertSentTo($confirmedUser, MatchVideoUploadedNotification::class);
    Notification::assertNotSentTo($declinedUser, MatchVideoUploadedNotification::class);
});
