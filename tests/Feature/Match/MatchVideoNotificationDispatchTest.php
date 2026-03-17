<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchVideoUploadedNotification;
use Illuminate\Support\Facades\Notification;

test('notifies all club members with ntfy when youtube url is added', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $memberWithNtfy = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithNtfy->id]);

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

    Notification::assertSentTo($memberWithNtfy, MatchVideoUploadedNotification::class);
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

    $memberWithNtfy = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithNtfy->id]);

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

test('notifies club members who did not attend the match when youtube url is added', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    // Member with ntfy but NOT an attendee of this match
    $nonAttendee = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $nonAttendee->id]);

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

    Notification::assertSentTo($nonAttendee, MatchVideoUploadedNotification::class);
});

test('does not notify club members without ntfy when youtube url is added', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $memberWithNtfy = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithNtfy->id]);

    $memberWithoutNtfy = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithoutNtfy->id]);

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

    Notification::assertSentTo($memberWithNtfy, MatchVideoUploadedNotification::class);
    Notification::assertNotSentTo($memberWithoutNtfy, MatchVideoUploadedNotification::class);
});
