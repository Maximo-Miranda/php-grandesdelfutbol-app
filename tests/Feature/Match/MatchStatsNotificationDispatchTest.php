<?php

use App\Enums\AttendanceStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;
use App\Notifications\MatchStatsFinalizedNotification;
use Illuminate\Support\Facades\Notification;

test('notifies confirmed attendees when stats are finalized', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $attendeeUser = User::factory()->create();
    $player = Player::factory()->linked($attendeeUser)->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => AttendanceStatus::Confirmed,
    ]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($attendeeUser, MatchStatsFinalizedNotification::class);
});

test('does not notify declined attendees when stats are finalized', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $confirmedUser = User::factory()->create();
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
        ->post(route('clubs.matches.finalizeStats', [$club, $match]))
        ->assertRedirect();

    Notification::assertSentTo($confirmedUser, MatchStatsFinalizedNotification::class);
    Notification::assertNotSentTo($declinedUser, MatchStatsFinalizedNotification::class);
});
