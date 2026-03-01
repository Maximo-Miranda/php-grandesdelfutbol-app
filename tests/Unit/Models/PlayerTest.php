<?php

use App\Models\Club;
use App\Models\Player;

test('player belongs to a club', function () {
    $player = Player::factory()->create();
    expect($player->club)->toBeInstanceOf(Club::class);
});

test('player can belong to a user', function () {
    $player = Player::factory()->linked()->create();
    expect($player->user)->not->toBeNull();
});

test('active scope returns only active players', function () {
    Player::factory()->create(['is_active' => true]);
    Player::factory()->inactive()->create();

    expect(Player::query()->active()->count())->toBe(1);
});

test('forClub scope filters by club', function () {
    $club = Club::factory()->create();
    Player::factory()->create(['club_id' => $club->id]);
    Player::factory()->create();

    expect(Player::query()->forClub($club)->count())->toBe(1);
});

test('player casts boolean and integer fields', function () {
    $player = Player::factory()->create(['is_active' => true, 'goals' => 5]);

    expect($player->is_active)->toBeTrue()
        ->and($player->goals)->toBeInt();
});
