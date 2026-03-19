<?php

use App\Enums\MatchEventType;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;

test('match event belongs to a match', function () {
    $event = MatchEvent::factory()->create();

    expect($event->match)->toBeInstanceOf(FootballMatch::class);
});

test('match event belongs to a player', function () {
    $event = MatchEvent::factory()->create();

    expect($event->player)->toBeInstanceOf(Player::class);
});

test('match event casts event_type to MatchEventType enum', function () {
    $event = MatchEvent::factory()->goal()->create();

    expect($event->event_type)->toBeInstanceOf(MatchEventType::class)
        ->and($event->event_type)->toBe(MatchEventType::Goal);
});

test('match event casts minute to integer', function () {
    $event = MatchEvent::factory()->create(['minute' => 45]);

    expect($event->minute)->toBeInt()
        ->and($event->minute)->toBe(45);
});

test('match event casts second to integer', function () {
    $event = MatchEvent::factory()->create(['second' => 30]);

    expect($event->second)->toBeInt()
        ->and($event->second)->toBe(30);
});

test('match event second defaults to zero', function () {
    $event = MatchEvent::factory()->create(['second' => 0]);

    expect($event->second)->toBe(0);
});

test('football match has many events', function () {
    $match = FootballMatch::factory()->create();
    MatchEvent::factory()->count(3)->create(['match_id' => $match->id]);

    expect($match->events)->toHaveCount(3);
});
