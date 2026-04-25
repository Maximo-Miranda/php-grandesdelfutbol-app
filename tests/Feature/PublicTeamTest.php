<?php

use App\Models\Club;
use App\Models\Season;
use App\Models\Team;

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
