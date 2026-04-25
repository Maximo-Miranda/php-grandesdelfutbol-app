<?php

use App\Models\Club;

test('guests can view a club public page by slug', function () {
    $club = Club::factory()->create([
        'name' => 'Club Ejemplo',
        'slug' => 'club-ejemplo',
        'description' => 'Un club de fútbol amateur.',
    ]);

    $this->get(route('club.public', $club->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Public')
            ->where('club.slug', 'club-ejemplo')
            ->where('club.name', 'Club Ejemplo')
            ->where('club.description', 'Un club de fútbol amateur.')
            ->has('club.logo_url')
            ->has('club.players_count')
            ->has('club.completed_matches_count')
            ->has('club.upcoming_matches_count')
            ->has('nextMatches')
            ->has('recentMatches')
            ->has('teams')
            ->has('appUrl')
        );
});

test('public club page 404s when slug does not exist', function () {
    $this->get('/club/inexistente')->assertNotFound();
});

test('public club page 404s when is_public is false', function () {
    $club = Club::factory()->create([
        'slug' => 'club-privado',
        'is_public' => false,
    ]);

    $this->get(route('club.public', $club->slug))->assertNotFound();
});

test('public club page returns 200 when is_public is true', function () {
    $club = Club::factory()->create([
        'slug' => 'club-publico',
        'is_public' => true,
    ]);

    $this->get(route('club.public', $club->slug))->assertOk();
});
