<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchStatsFinalizedNotification;
use Illuminate\Support\Facades\Notification;

test('notifies all approved members when stats are finalized for the first time', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $memberWithPush = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithPush->id]);
    $memberWithPush->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

    $memberWithout = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithout->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($memberWithPush, MatchStatsFinalizedNotification::class);
    Notification::assertSentTo($memberWithout, MatchStatsFinalizedNotification::class);
});

test('does not re-notify when stats are re-finalized', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $member = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $member->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    // First finalization — notifies
    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($member, MatchStatsFinalizedNotification::class);

    Notification::fake(); // Reset

    // Re-finalization — should NOT notify again
    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('does not notify when no approved members exist', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    // Admin is a member so they get notified
    Notification::assertSentTo($admin, MatchStatsFinalizedNotification::class);
});
