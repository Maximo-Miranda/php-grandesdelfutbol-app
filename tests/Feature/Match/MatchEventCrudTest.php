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

test('admins can record a match event with second', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 45,
            'second' => 30,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => 'goal',
        'minute' => 45,
        'second' => 30,
    ]);
});

test('event without second defaults to zero', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'assist',
            'minute' => 10,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'minute' => 10,
        'second' => 0,
    ]);
});

test('second validation rejects values outside 0-59', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 45,
            'second' => 60,
        ])
        ->assertSessionHasErrors(['second']);
});

test('event store validates required fields', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [])
        ->assertSessionHasErrors(['event_type', 'minute']);
});

test('player event without player or team fails', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'goal',
            'minute' => 10,
        ])
        ->assertSessionHasErrors(['team']);
});

test('player event with team only (no player) succeeds', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'goal',
            'team' => 'a',
            'minute' => 25,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'event_type' => 'goal',
        'team' => 'a',
        'player_id' => null,
    ]);
});

test('admin can assign player to existing event', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->create([
        'match_id' => $match->id,
        'player_id' => null,
        'team' => 'a',
        'event_type' => 'goal',
    ]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.events.update', [$club, $match, $event]), [
            'player_id' => $player->id,
        ])
        ->assertRedirect();

    $event->refresh();
    expect($event->player_id)->toBe($player->id);
});

test('admin can fully edit an event via PUT', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->create([
        'match_id' => $match->id,
        'event_type' => 'foul',
        'team' => 'a',
        'minute' => 10,
        'second' => 30,
    ]);

    $this->actingAs($user)
        ->put(route('clubs.matches.events.fullUpdate', [$club, $match, $event]), [
            'event_type' => 'yellow_card',
            'player_id' => $player->id,
            'team' => 'a',
            'minute' => 15,
            'second' => 45,
        ])
        ->assertRedirect();

    $event->refresh();
    expect($event->event_type->value)->toBe('yellow_card')
        ->and($event->player_id)->toBe($player->id)
        ->and($event->minute)->toBe(15)
        ->and($event->second)->toBe(45);
});

test('full update validates event scope rules', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->create([
        'match_id' => $match->id,
        'event_type' => 'goal',
        'team' => 'a',
        'minute' => 10,
        'second' => 0,
    ]);

    // Change to team event without team → should fail
    $this->actingAs($user)
        ->put(route('clubs.matches.events.fullUpdate', [$club, $match, $event]), [
            'event_type' => 'shot_on_target',
            'minute' => 10,
            'second' => 0,
        ])
        ->assertSessionHasErrors('team');
});

test('team event requires team', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'shot_on_target',
            'minute' => 10,
        ])
        ->assertSessionHasErrors(['team']);
});

test('team event accepts optional player_id', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'corner_kick',
            'player_id' => $player->id,
            'team' => 'a',
            'minute' => 10,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'event_type' => 'corner_kick',
        'team' => 'a',
        'player_id' => $player->id,
    ]);
});

test('neutral event rejects player_id and team', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'timeout',
            'player_id' => $player->id,
            'team' => 'a',
            'minute' => 10,
        ])
        ->assertSessionHasErrors(['player_id', 'team']);
});

test('admins can create a team event', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'shot_on_target',
            'team' => 'a',
            'minute' => 25,
            'second' => 10,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'event_type' => 'shot_on_target',
        'team' => 'a',
        'player_id' => null,
        'minute' => 25,
        'second' => 10,
    ]);
});

test('admins can create a neutral event', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'event_type' => 'water_break',
            'minute' => 45,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'event_type' => 'water_break',
        'player_id' => null,
        'team' => null,
    ]);
});
