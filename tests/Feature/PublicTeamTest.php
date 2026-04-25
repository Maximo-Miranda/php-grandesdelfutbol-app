<?php

use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerProfile;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

test('guests can view a team public page by ulid', function () {
    $club = Club::factory()->create(['name' => 'Club Ejemplo', 'slug' => 'club-ejemplo']);
    $season = Season::factory()->create(['club_id' => $club->id]);
    $team = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'name' => 'Los Pibes',
        'color' => '#ff0000',
    ]);

    $this->get(route('team.public', $team->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Public')
            ->where('team.name', 'Los Pibes')
            ->where('team.color', '#ff0000')
            ->where('club.slug', 'club-ejemplo')
            ->has('team.players')
            ->has('team.season.name')
            ->has('stats.Pts')
            ->has('recentMatches')
        );
});

test('public team page 404s when ulid does not exist', function () {
    $this->get('/team/01AAAAAAAAAAAAAAAAAAAAAAAA')->assertNotFound();
});

test('public team page 404s when parent club is not public', function () {
    $club = Club::factory()->create(['is_public' => false]);
    $season = Season::factory()->create(['club_id' => $club->id]);
    $team = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $this->get(route('team.public', $team->ulid))->assertNotFound();
});

test('private profile players are hidden from public team roster', function () {
    $club = Club::factory()->create(['is_public' => true]);
    $season = Season::factory()->create(['club_id' => $club->id]);
    $team = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $publicUser = User::factory()->create();
    PlayerProfile::factory()->create(['user_id' => $publicUser->id, 'is_public_profile' => true, 'nickname' => 'VisibleNick']);
    $publicPlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $publicUser->id]);
    $team->players()->attach($publicPlayer->id);

    $privateUser = User::factory()->create();
    PlayerProfile::factory()->create(['user_id' => $privateUser->id, 'is_public_profile' => false, 'nickname' => 'HiddenNick']);
    $privatePlayer = Player::factory()->create(['club_id' => $club->id, 'user_id' => $privateUser->id]);
    $team->players()->attach($privatePlayer->id);

    $this->get(route('team.public', $team->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('team.players_count', 1)
            ->where('team.players.0.ulid', $publicPlayer->ulid)
            ->where('team.players', fn ($players) => collect($players)->pluck('ulid')->doesntContain($privatePlayer->ulid))
        );
});
