<?php

use App\Enums\AttachmentCollection;

test('it has the correct values', function () {
    expect(AttachmentCollection::Logo->value)->toBe('logo')
        ->and(AttachmentCollection::Photo->value)->toBe('photo');
});

test('it can be created from value', function () {
    expect(AttachmentCollection::from('logo'))->toBe(AttachmentCollection::Logo)
        ->and(AttachmentCollection::from('photo'))->toBe(AttachmentCollection::Photo);
});

test('tryFrom returns null for invalid value', function () {
    expect(AttachmentCollection::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(AttachmentCollection::Logo->label())->toBe('Logo')
        ->and(AttachmentCollection::Photo->label())->toBe('Photo');
});
