<?php

use App\Models\Club;
use App\Models\Player;
use App\Services\ClubContext;

test('scope filters by club_id when context is set', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    Player::factory()->create(['club_id' => $clubA->id]);
    Player::factory()->create(['club_id' => $clubB->id]);

    app(ClubContext::class)->set($clubA);

    $players = Player::all();

    expect($players)->toHaveCount(1);
    expect($players->first()->club_id)->toBe($clubA->id);
});

test('scope does not filter when no context is set', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    Player::factory()->create(['club_id' => $clubA->id]);
    Player::factory()->create(['club_id' => $clubB->id]);

    $players = Player::all();

    expect($players)->toHaveCount(2);
});

test('clearing context disables scope', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    Player::factory()->create(['club_id' => $clubA->id]);
    Player::factory()->create(['club_id' => $clubB->id]);

    $context = app(ClubContext::class);
    $context->set($clubA);

    expect(Player::all())->toHaveCount(1);

    $context->clear();

    expect(Player::all())->toHaveCount(2);
});

test('scope can be removed with withoutGlobalScopes', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    Player::factory()->create(['club_id' => $clubA->id]);
    Player::factory()->create(['club_id' => $clubB->id]);

    app(ClubContext::class)->set($clubA);

    $allPlayers = Player::withoutGlobalScopes()->get();

    expect($allPlayers)->toHaveCount(2);
});
