<?php

use App\Jobs\PublishClubNtfy;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchVideoUploadedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

test('dispatches web push and ntfy when youtube url is added', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $memberWithPush = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithPush->id]);
    $memberWithPush->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

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

    Notification::assertSentTo($memberWithPush, MatchVideoUploadedNotification::class);
    Bus::assertDispatched(PublishClubNtfy::class, function ($job) use ($club) {
        return $job->club->id === $club->id;
    });
});

test('does not notify when youtube url already existed', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => 'https://www.youtube.com/watch?v=existing',
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
    Bus::assertNotDispatched(PublishClubNtfy::class);
});

test('does not notify when update does not include youtube url', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

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
    Bus::assertNotDispatched(PublishClubNtfy::class);
});

test('does not send web push to members without push subscription', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $memberWithPush = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithPush->id]);
    $memberWithPush->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

    $memberWithoutPush = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithoutPush->id]);

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

    Notification::assertSentTo($memberWithPush, MatchVideoUploadedNotification::class);
    Notification::assertNotSentTo($memberWithoutPush, MatchVideoUploadedNotification::class);
    Bus::assertDispatched(PublishClubNtfy::class);
});
