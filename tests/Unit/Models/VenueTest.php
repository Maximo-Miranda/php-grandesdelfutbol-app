<?php

use App\Models\Club;
use App\Models\Field;
use App\Models\Venue;

test('venue belongs to a club', function () {
    $venue = Venue::factory()->create();
    expect($venue->club)->toBeInstanceOf(Club::class);
});

test('venue has many fields', function () {
    $venue = Venue::factory()->create();
    Field::factory()->create(['venue_id' => $venue->id]);
    Field::factory()->create(['venue_id' => $venue->id]);

    expect($venue->fields)->toHaveCount(2)
        ->and($venue->fields->first())->toBeInstanceOf(Field::class);
});

test('active scope returns only active venues', function () {
    Venue::factory()->create(['is_active' => true]);
    Venue::factory()->inactive()->create();

    expect(Venue::query()->active()->count())->toBe(1);
});

test('venue casts is_active to boolean', function () {
    $venue = Venue::factory()->create(['is_active' => true]);

    expect($venue->is_active)->toBeTrue()->toBeBool();
});
