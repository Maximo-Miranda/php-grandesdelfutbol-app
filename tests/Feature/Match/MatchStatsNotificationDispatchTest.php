<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use App\Notifications\MatchStatsFinalizedNotification;
use Illuminate\Support\Facades\Notification;

test('notifies all club members with ntfy when stats are finalized', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $memberWithNtfy = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithNtfy->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($memberWithNtfy, MatchStatsFinalizedNotification::class);
});

test('does not notify club members without ntfy enabled when stats are finalized', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $memberWithNtfy = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithNtfy->id]);

    $memberWithoutNtfy = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $memberWithoutNtfy->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($memberWithNtfy, MatchStatsFinalizedNotification::class);
    Notification::assertNotSentTo($memberWithoutNtfy, MatchStatsFinalizedNotification::class);
});

test('notifies club members who did not attend the match when stats are finalized', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    // Member with ntfy but NOT an attendee of this match
    $nonAttendee = User::factory()->withNtfy()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $nonAttendee->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($nonAttendee, MatchStatsFinalizedNotification::class);
});
