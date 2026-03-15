<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('player-card'))
        ->assertRedirect(route('login'));
});

test('player card renders with stats', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
        'goals' => 7,
        'assists' => 4,
        'matches_played' => 12,
        'yellow_cards' => 2,
        'red_cards' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('player-card'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PlayerCard')
            ->where('playerStats.goals', 7)
            ->where('playerStats.matches', 12)
            ->where('playerStats.yellowCards', 2)
            ->where('playerStats.redCards', 1)
            ->has('clubs', 1)
            ->has('profile')
        );
});

test('player card aggregates stats across multiple clubs', function () {
    $user = User::factory()->create();

    $club1 = Club::factory()->create();
    $club2 = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club1->id, 'user_id' => $user->id]);
    ClubMember::factory()->create(['club_id' => $club2->id, 'user_id' => $user->id]);

    Player::factory()->create(['club_id' => $club1->id, 'user_id' => $user->id, 'goals' => 3, 'assists' => 2, 'matches_played' => 5]);
    Player::factory()->create(['club_id' => $club2->id, 'user_id' => $user->id, 'goals' => 4, 'assists' => 1, 'matches_played' => 8]);

    $this->actingAs($user)
        ->get(route('player-card'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('clubs', 2)
            ->where('playerStats.goals', 7)
            ->where('playerStats.matches', 13)
        );
});
