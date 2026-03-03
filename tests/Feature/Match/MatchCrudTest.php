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

test('admins can delete matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.destroy', [$club, $match]))
        ->assertRedirect(route('clubs.matches.index', $club));

    $this->assertDatabaseMissing('matches', ['id' => $match->id]);
});

test('admins can create matches with team config', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.store', $club), [
            'title' => 'Team Config Match',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'team_a_name' => 'Los Rojos',
            'team_b_name' => 'Los Azules',
            'team_a_color' => '#dc2626',
            'team_b_color' => '#2563eb',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('matches', [
        'club_id' => $club->id,
        'title' => 'Team Config Match',
        'team_a_name' => 'Los Rojos',
        'team_b_name' => 'Los Azules',
        'team_a_color' => '#dc2626',
        'team_b_color' => '#2563eb',
    ]);
});

test('admins can update matches with team config', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toISOString(),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => 24,
            'team_a_name' => 'Blancos',
            'team_b_name' => 'Negros',
            'team_a_color' => '#ffffff',
            'team_b_color' => '#1a1a1a',
        ])
        ->assertRedirect();

    $match->refresh();
    expect($match->team_a_name)->toBe('Blancos')
        ->and($match->team_b_name)->toBe('Negros')
        ->and($match->team_a_color)->toBe('#ffffff')
        ->and($match->team_b_color)->toBe('#1a1a1a');
});

test('team names cannot use reserved role words', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $reservedNames = ['Titulares', 'Suplentes', 'Titular', 'Suplente'];

    foreach ($reservedNames as $name) {
        $this->actingAs($user)
            ->post(route('clubs.matches.store', $club), [
                'title' => 'Match',
                'scheduled_at' => now()->addDay()->toISOString(),
                'duration_minutes' => 60,
                'arrival_minutes' => 15,
                'max_players' => 10,
                'max_substitutes' => 4,
                'registration_opens_hours' => 24,
                'team_a_name' => $name,
            ])
            ->assertSessionHasErrors('team_a_name');

        $this->actingAs($user)
            ->post(route('clubs.matches.store', $club), [
                'title' => 'Match',
                'scheduled_at' => now()->addDay()->toISOString(),
                'duration_minutes' => 60,
                'arrival_minutes' => 15,
                'max_players' => 10,
                'max_substitutes' => 4,
                'registration_opens_hours' => 24,
                'team_b_name' => $name,
            ])
            ->assertSessionHasErrors('team_b_name');
    }
});

test('regular members cannot delete matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.destroy', [$club, $match]))
        ->assertForbidden();

    $this->assertDatabaseHas('matches', ['id' => $match->id]);
});
