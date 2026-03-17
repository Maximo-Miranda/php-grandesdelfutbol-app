<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\PlayerProfile;
use App\Models\User;
use Carbon\Carbon;

test('club show includes birthdays for the current month', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $birthdayUser = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $birthdayUser->id]);
    PlayerProfile::factory()->create([
        'user_id' => $birthdayUser->id,
        'date_of_birth' => Carbon::now()->startOfMonth()->addDays(10),
    ]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Show')
            ->has('birthdays', 1)
            ->where('birthdays.0.name', $birthdayUser->name)
        );
});

test('club show excludes birthdays from other months', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $otherMonthUser = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $otherMonthUser->id]);
    PlayerProfile::factory()->create([
        'user_id' => $otherMonthUser->id,
        'date_of_birth' => Carbon::now()->addMonths(3)->startOfMonth(),
    ]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Show')
            ->has('birthdays', 0)
        );
});

test('club show excludes members without a date of birth', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $noDobUser = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $noDobUser->id]);
    PlayerProfile::factory()->create([
        'user_id' => $noDobUser->id,
        'date_of_birth' => null,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Show')
            ->has('birthdays', 0)
        );
});

test('club show sorts birthdays closest upcoming first', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $today = Carbon::now();

    // Birthday tomorrow (closest upcoming)
    $tomorrowUser = User::factory()->create(['name' => 'Tomorrow Birthday']);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $tomorrowUser->id]);
    PlayerProfile::factory()->create([
        'user_id' => $tomorrowUser->id,
        'date_of_birth' => $today->copy()->addDay()->year(1990),
    ]);

    // Birthday in 5 days (further away)
    $laterUser = User::factory()->create(['name' => 'Later Birthday']);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $laterUser->id]);
    PlayerProfile::factory()->create([
        'user_id' => $laterUser->id,
        'date_of_birth' => $today->copy()->addDays(5)->year(1990),
    ]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Show')
            ->has('birthdays', 2)
            ->where('birthdays.0.name', 'Tomorrow Birthday')
            ->where('birthdays.1.name', 'Later Birthday')
        );
});
