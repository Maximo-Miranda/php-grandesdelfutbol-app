<?php

use App\Enums\FieldType;
use App\Models\Field;
use App\Models\Venue;

test('field belongs to a venue', function () {
    $field = Field::factory()->create();
    expect($field->venue)->toBeInstanceOf(Venue::class);
});

test('field casts field_type to FieldType enum', function () {
    $field = Field::factory()->create(['field_type' => '5v5']);

    expect($field->field_type)->toBeInstanceOf(FieldType::class)
        ->and($field->field_type)->toBe(FieldType::FiveVsFive);
});

test('field casts is_active to boolean', function () {
    $field = Field::factory()->create(['is_active' => true]);

    expect($field->is_active)->toBeTrue()->toBeBool();
});
