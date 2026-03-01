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
