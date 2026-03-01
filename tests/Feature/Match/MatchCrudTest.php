<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;

test('club members can view matches index', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.index', $club))
        ->assertOk();
});

test('admins can create matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Sunday Match',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 90,
            'arrival_minutes' => 15,
            'max_players' => 14,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('matches', [
        'club_id' => $club->id,
        'title' => 'Sunday Match',
    ]);
});

test('members can view a match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.show', [$club, $match]))
        ->assertOk();
});

test('admins can update matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => 'Updated Title',
            'scheduled_at' => $match->scheduled_at->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
        ])
        ->assertRedirect();

    $match->refresh();
    expect($match->title)->toBe('Updated Title');
});
