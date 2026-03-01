<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\User;

test('admins can record a match event', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 25,
            'notes' => 'Header from corner',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => 'goal',
        'minute' => 25,
    ]);
});

test('admins can remove a match event', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.events.destroy', [$club, $match, $event]))
        ->assertRedirect();

    $this->assertDatabaseMissing('match_events', ['id' => $event->id]);
});

test('non-admin members cannot record events', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 10,
        ])
        ->assertForbidden();
});

test('event store validates required fields', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [])
        ->assertSessionHasErrors(['player_id', 'event_type', 'minute']);
});
