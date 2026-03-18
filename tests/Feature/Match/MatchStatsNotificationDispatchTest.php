<?php

use App\Jobs\PublishClubNtfy;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchStatsFinalizedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

test('dispatches web push and ntfy when stats are finalized', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Bus::assertDispatched(PublishClubNtfy::class, function ($job) use ($club) {
        return $job->club->id === $club->id;
    });
});

test('sends web push to members with push subscriptions when stats are finalized', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    // Member with push subscription
    $memberWithPush = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithPush->id]);
    $memberWithPush->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

    // Member without push subscription
    $memberWithout = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithout->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($memberWithPush, MatchStatsFinalizedNotification::class);
    Notification::assertNotSentTo($memberWithout, MatchStatsFinalizedNotification::class);
});

test('always dispatches ntfy job even when no push subscriptions exist', function () {
    Notification::fake();
    Bus::fake([PublishClubNtfy::class]);

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertNothingSent();
    Bus::assertDispatched(PublishClubNtfy::class);
});
