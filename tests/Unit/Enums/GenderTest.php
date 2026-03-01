<?php

use App\Enums\Gender;

test('it has the correct values', function () {
    expect(Gender::Male->value)->toBe('male')
        ->and(Gender::Female->value)->toBe('female')
        ->and(Gender::Other->value)->toBe('other');
});

test('it can be created from value', function () {
    expect(Gender::from('male'))->toBe(Gender::Male)
        ->and(Gender::from('female'))->toBe(Gender::Female)
        ->and(Gender::from('other'))->toBe(Gender::Other);
});

test('tryFrom returns null for invalid value', function () {
    expect(Gender::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(Gender::Male->label())->toBe('Male')
        ->and(Gender::Female->label())->toBe('Female')
        ->and(Gender::Other->label())->toBe('Other');
});
