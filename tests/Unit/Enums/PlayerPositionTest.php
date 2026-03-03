<?php

use App\Enums\PlayerPosition;

test('it has the correct values', function () {
    expect(PlayerPosition::Gk->value)->toBe('GK')
        ->and(PlayerPosition::Cb->value)->toBe('CB')
        ->and(PlayerPosition::Lb->value)->toBe('LB')
        ->and(PlayerPosition::Rb->value)->toBe('RB')
        ->and(PlayerPosition::Cdm->value)->toBe('CDM')
        ->and(PlayerPosition::Cm->value)->toBe('CM')
        ->and(PlayerPosition::Cam->value)->toBe('CAM')
        ->and(PlayerPosition::Lw->value)->toBe('LW')
        ->and(PlayerPosition::Rw->value)->toBe('RW')
        ->and(PlayerPosition::St->value)->toBe('ST')
        ->and(PlayerPosition::Cf->value)->toBe('CF');
});

test('it has labels in Spanish', function () {
    expect(PlayerPosition::Gk->label())->toBe('Portero')
        ->and(PlayerPosition::Cb->label())->toBe('Central')
        ->and(PlayerPosition::Lb->label())->toBe('Lateral Izq.')
        ->and(PlayerPosition::Rb->label())->toBe('Lateral Der.')
        ->and(PlayerPosition::Cdm->label())->toBe('Mediocampista Def.')
        ->and(PlayerPosition::Cm->label())->toBe('Mediocampista')
        ->and(PlayerPosition::Cam->label())->toBe('Mediocampista Of.')
        ->and(PlayerPosition::Lw->label())->toBe('Extremo Izq.')
        ->and(PlayerPosition::Rw->label())->toBe('Extremo Der.')
        ->and(PlayerPosition::St->label())->toBe('Delantero')
        ->and(PlayerPosition::Cf->label())->toBe('Centro Delantero');
});

test('it can be created from value', function () {
    expect(PlayerPosition::from('GK'))->toBe(PlayerPosition::Gk)
        ->and(PlayerPosition::from('ST'))->toBe(PlayerPosition::St);
});

test('tryFrom returns null for invalid value', function () {
    expect(PlayerPosition::tryFrom('invalid'))->toBeNull();
});

test('it has 11 cases', function () {
    expect(PlayerPosition::cases())->toHaveCount(11);
});
