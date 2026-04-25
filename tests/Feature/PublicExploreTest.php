<?php

use App\Models\Club;
use App\Models\FootballMatch;

test('guests can view the explore page', function () {
    $this->get(route('explore.public'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('explore/Public')
            ->has('clubs.data')
            ->where('search', '')
        );
});

test('explore page only lists public clubs', function () {
    Club::factory()->create(['name' => 'Club Publico', 'slug' => 'club-publico', 'is_public' => true]);
    Club::factory()->create(['name' => 'Club Privado', 'slug' => 'club-privado', 'is_public' => false]);

    $this->get(route('explore.public'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('explore/Public')
            ->where('clubs.data.0.slug', 'club-publico')
            ->where('clubs.data', fn ($data) => collect($data)->pluck('slug')->doesntContain('club-privado'))
        );
});

test('explore page search filters by name case-insensitively', function () {
    Club::factory()->create(['name' => 'Los Pibes', 'slug' => 'los-pibes', 'is_public' => true]);
    Club::factory()->create(['name' => 'Aguilas FC', 'slug' => 'aguilas-fc', 'is_public' => true]);

    $this->get(route('explore.public', ['q' => 'pibe']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('search', 'pibe')
            ->where('clubs.data.0.slug', 'los-pibes')
            ->count('clubs.data', 1)
        );
});

test('explore page orders clubs by most recent activity', function () {
    $quiet = Club::factory()->create(['name' => 'Club Quieto', 'slug' => 'club-quieto', 'is_public' => true]);
    $active = Club::factory()->create(['name' => 'Club Activo', 'slug' => 'club-activo', 'is_public' => true]);
    $recent = Club::factory()->create(['name' => 'Club Reciente', 'slug' => 'club-reciente', 'is_public' => true]);

    FootballMatch::factory()->create(['club_id' => $quiet->id, 'scheduled_at' => now()->subMonths(3)]);
    FootballMatch::factory()->create(['club_id' => $active->id, 'scheduled_at' => now()->subDays(7)]);
    FootballMatch::factory()->create(['club_id' => $recent->id, 'scheduled_at' => now()->addDays(2)]);

    $this->get(route('explore.public'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('clubs.data.0.slug', 'club-reciente')
            ->where('clubs.data.1.slug', 'club-activo')
            ->where('clubs.data.2.slug', 'club-quieto')
        );
});

test('clubs without matches appear after clubs with activity', function () {
    $withMatch = Club::factory()->create(['name' => 'Club Con Partidos', 'slug' => 'con-partidos', 'is_public' => true]);
    $noMatches = Club::factory()->create(['name' => 'Club Sin Partidos', 'slug' => 'sin-partidos', 'is_public' => true]);

    FootballMatch::factory()->create(['club_id' => $withMatch->id, 'scheduled_at' => now()->subDays(1)]);

    $this->get(route('explore.public'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('clubs.data.0.slug', 'con-partidos')
            ->where('clubs.data.1.slug', 'sin-partidos')
        );
});

test('explore page exposes safe club fields only', function () {
    Club::factory()->create(['slug' => 'tiny-club', 'is_public' => true]);

    $this->get(route('explore.public'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('clubs.data.0', fn ($club) => $club
                ->has('ulid')
                ->has('slug')
                ->has('name')
                ->has('description')
                ->has('logo_url')
                ->has('completed_matches_count')
                ->has('upcoming_matches_count')
                ->has('players_count')
                ->missing('owner_id')
                ->missing('invite_token')
                ->missing('is_public')
            )
        );
});
