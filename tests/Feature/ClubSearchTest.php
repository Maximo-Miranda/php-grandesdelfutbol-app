<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('returns paginated clubs for authenticated user', function () {
    $user = User::factory()->create();
    $clubs = Club::factory(5)->create();

    foreach ($clubs as $club) {
        ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    }

    $response = $this->actingAs($user)
        ->getJson(route('clubs.search'))
        ->assertSuccessful();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('next_page_url'))->not->toBeNull();
});

test('returns specific clubs by ids', function () {
    $user = User::factory()->create();
    $clubs = Club::factory(5)->create();

    foreach ($clubs as $club) {
        ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    }

    $ids = $clubs->take(2)->pluck('id')->join(',');

    $this->actingAs($user)
        ->getJson(route('clubs.search', ['ids' => $ids]))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('only returns clubs user belongs to', function () {
    $user = User::factory()->create();
    $myClub = Club::factory()->create();
    Club::factory()->create(); // club user doesn't belong to

    ClubMember::factory()->create(['club_id' => $myClub->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson(route('clubs.search'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('requires authentication', function () {
    $this->getJson(route('clubs.search'))
        ->assertUnauthorized();
});
