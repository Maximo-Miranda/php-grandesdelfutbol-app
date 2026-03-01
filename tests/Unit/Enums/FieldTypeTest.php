<?php

use App\Enums\FieldType;

test('it has the correct values', function () {
    expect(FieldType::FiveVsFive->value)->toBe('5v5')
        ->and(FieldType::SixVsSix->value)->toBe('6v6')
        ->and(FieldType::SevenVsSeven->value)->toBe('7v7')
        ->and(FieldType::EightVsEight->value)->toBe('8v8')
        ->and(FieldType::NineVsNine->value)->toBe('9v9')
        ->and(FieldType::TenVsTen->value)->toBe('10v10')
        ->and(FieldType::ElevenVsEleven->value)->toBe('11v11');
});

test('it can be created from value', function () {
    expect(FieldType::from('5v5'))->toBe(FieldType::FiveVsFive)
        ->and(FieldType::from('6v6'))->toBe(FieldType::SixVsSix)
        ->and(FieldType::from('7v7'))->toBe(FieldType::SevenVsSeven)
        ->and(FieldType::from('8v8'))->toBe(FieldType::EightVsEight)
        ->and(FieldType::from('9v9'))->toBe(FieldType::NineVsNine)
        ->and(FieldType::from('10v10'))->toBe(FieldType::TenVsTen)
        ->and(FieldType::from('11v11'))->toBe(FieldType::ElevenVsEleven);
});

test('tryFrom returns null for invalid value', function () {
    expect(FieldType::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(FieldType::FiveVsFive->label())->toBe('5 vs 5')
        ->and(FieldType::SixVsSix->label())->toBe('6 vs 6')
        ->and(FieldType::SevenVsSeven->label())->toBe('7 vs 7')
        ->and(FieldType::EightVsEight->label())->toBe('8 vs 8')
        ->and(FieldType::NineVsNine->label())->toBe('9 vs 9')
        ->and(FieldType::TenVsTen->label())->toBe('10 vs 10')
        ->and(FieldType::ElevenVsEleven->label())->toBe('11 vs 11');
});
