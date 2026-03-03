<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;

test('admins can auto-assign teams', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id, 'max_players' => 4]);

    $players = Player::factory()->count(4)->create(['club_id' => $club->id]);
    foreach ($players as $player) {
        MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    $match->load('attendances');
    expect($match->attendances->whereNotNull('team')->count())->toBe(4);
});

test('admins can update individual attendance', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'team' => 'a',
            'role' => 'starter',
        ])
        ->assertRedirect();

    $attendance->refresh();
    expect($attendance->team->value)->toBe('a');
});

test('admins can unconfirm a confirmed player', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->starter()->teamA()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'status' => 'declined',
        ])
        ->assertRedirect();

    $attendance->refresh();
    expect($attendance->status->value)->toBe('declined')
        ->and($attendance->role->value)->toBe('pending')
        ->and($attendance->team)->toBeNull()
        ->and($attendance->confirmed_at)->toBeNull();
});

test('admins can remove a player from the match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.attendance.destroy', [$club, $match, $attendance]))
        ->assertRedirect();

    $this->assertDatabaseMissing('match_attendances', ['id' => $attendance->id]);
});

test('admins can reconfirm a declined player', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id, 'max_players' => 10]);
    $attendance = MatchAttendance::factory()->declined()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $attendance->refresh();
    expect($attendance->status->value)->toBe('confirmed')
        ->and($attendance->role->value)->toBe('starter')
        ->and($attendance->confirmed_at)->not->toBeNull();
});
