<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\PlayerProfile;
use App\Models\User;

test('display_name returns nickname when player has a profile with nickname', function () {
    $user = User::factory()->create();
    PlayerProfile::factory()->create(['user_id' => $user->id, 'nickname' => 'El Diez']);
    $player = Player::factory()->create(['user_id' => $user->id, 'name' => 'Juan Perez']);

    $player->load('user.playerProfile');

    expect($player->display_name)->toBe('El Diez');
});

test('display_name falls back to name when no profile exists', function () {
    $player = Player::factory()->create(['name' => 'Carlos Lopez', 'user_id' => null]);

    expect($player->display_name)->toBe('Carlos Lopez');
});

test('display_name falls back to name when nickname is null', function () {
    $user = User::factory()->create();
    PlayerProfile::factory()->create(['user_id' => $user->id, 'nickname' => null]);
    $player = Player::factory()->create(['user_id' => $user->id, 'name' => 'Maria Garcia']);

    $player->load('user.playerProfile');

    expect($player->display_name)->toBe('Maria Garcia');
});

test('player show returns lastGoal prop when player has scored', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'title' => 'Final Cup',
    ]);

    MatchEvent::factory()->goal()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'minute' => 42,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.players.show', [$club, $player]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Show')
            ->has('lastGoal')
            ->where('lastGoal.match_title', 'Final Cup')
            ->where('lastGoal.minute', 42)
        );
});

test('player show returns null lastGoal when player has no goals', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.players.show', [$club, $player]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Show')
            ->where('lastGoal', null)
        );
});

test('player show returns attendanceRate prop', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    // Create 4 completed matches
    $matches = FootballMatch::factory()->completed()->count(4)->create(['club_id' => $club->id]);

    // Player attended 3 of 4
    foreach ($matches->take(3) as $match) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
        ]);
    }

    $this->actingAs($user)
        ->get(route('clubs.players.show', [$club, $player]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Show')
            ->where('attendanceRate', 75)
        );
});

test('player show returns null attendanceRate when no completed matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.players.show', [$club, $player]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Show')
            ->where('attendanceRate', null)
        );
});

test('player index returns display_name from nickname', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $playerUser = User::factory()->create();
    PlayerProfile::factory()->create(['user_id' => $playerUser->id, 'nickname' => 'Speedy']);
    Player::factory()->create(['club_id' => $club->id, 'user_id' => $playerUser->id, 'name' => 'John Fast']);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Index')
            ->has('players.data', 1)
            ->where('players.data.0.display_name', 'Speedy')
        );
});
