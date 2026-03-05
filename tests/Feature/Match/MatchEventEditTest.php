<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\User;

test('admin can add events to completed matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.events.store', [$club, $match]), [
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 25,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_events', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => 'goal',
        'minute' => 25,
    ]);
});

test('admin can remove events from completed matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => 'goal',
        'minute' => 10,
    ]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.events.destroy', [$club, $match, $event]))
        ->assertRedirect();

    $this->assertDatabaseMissing('match_events', ['id' => $event->id]);
});

test('completed match summary includes players for admin', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    Player::factory()->create(['club_id' => $club->id, 'is_active' => true]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->has('players', 1)
        );
});
