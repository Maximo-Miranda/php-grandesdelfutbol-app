<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->user->id]);
});

test('matches index returns paginated data', function () {
    FootballMatch::factory()->count(3)->create(['club_id' => $this->club->id]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', $this->club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Index')
            ->has('matches.data', 3)
            ->where('filter', 'all')
        );
});

test('upcoming filter shows only upcoming and in-progress matches', function () {
    FootballMatch::factory()->create(['club_id' => $this->club->id]);
    FootballMatch::factory()->inProgress()->create(['club_id' => $this->club->id]);
    FootballMatch::factory()->completed()->create(['club_id' => $this->club->id]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', [$this->club, 'filter' => 'upcoming']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('matches.data', 2)
            ->where('filter', 'upcoming')
        );
});

test('completed filter shows only completed matches', function () {
    FootballMatch::factory()->create(['club_id' => $this->club->id]);
    FootballMatch::factory()->completed()->count(2)->create(['club_id' => $this->club->id]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', [$this->club, 'filter' => 'completed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('matches.data', 2)
            ->where('filter', 'completed')
        );
});

test('upcoming matches are ordered closest first', function () {
    $far = FootballMatch::factory()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->addDays(10),
    ]);
    $near = FootballMatch::factory()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', [$this->club, 'filter' => 'upcoming']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('matches.data.0.id', $near->id)
            ->where('matches.data.1.id', $far->id)
        );
});

test('completed matches are ordered most recent first', function () {
    $older = FootballMatch::factory()->completed()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->subDays(5),
    ]);
    $newer = FootballMatch::factory()->completed()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->subDay(),
    ]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', [$this->club, 'filter' => 'completed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('matches.data.0.id', $newer->id)
            ->where('matches.data.1.id', $older->id)
        );
});

test('all tab orders completed most recent first within their group', function () {
    $oldCompleted = FootballMatch::factory()->completed()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->subDays(5),
    ]);
    $recentCompleted = FootballMatch::factory()->completed()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->subDay(),
    ]);
    $upcoming = FootballMatch::factory()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', $this->club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('matches.data.0.id', $upcoming->id)
            ->where('matches.data.1.id', $recentCompleted->id)
            ->where('matches.data.2.id', $oldCompleted->id)
        );
});

test('all tab shows upcoming before completed', function () {
    $completed = FootballMatch::factory()->completed()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->subDay(),
    ]);
    $upcoming = FootballMatch::factory()->create([
        'club_id' => $this->club->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($this->user)
        ->get(route('clubs.matches.index', $this->club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('matches.data.0.id', $upcoming->id)
            ->where('matches.data.1.id', $completed->id)
        );
});
