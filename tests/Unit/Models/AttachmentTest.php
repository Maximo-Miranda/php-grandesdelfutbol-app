<?php

use App\Enums\AttachmentCollection;
use App\Models\Attachment;
use App\Models\Club;

test('attachment belongs to attachable via morph', function () {
    $club = Club::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Club::class,
        'attachable_id' => $club->id,
    ]);

    expect($attachment->attachable)->toBeInstanceOf(Club::class)
        ->and($attachment->attachable->id)->toBe($club->id);
});

test('attachment casts collection to enum', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->collection)->toBeInstanceOf(AttachmentCollection::class);
});

test('attachment has url accessor', function () {
    $attachment = Attachment::factory()->create([
        'disk' => 'public',
        'path' => 'clubs/1/logo/test.png',
    ]);

    expect($attachment->url)->toBeString()
        ->and($attachment->url)->toContain('clubs/1/logo/test.png');
});

test('club has attachments via HasAttachments trait', function () {
    $club = Club::factory()->create();
    Attachment::factory()->create([
        'attachable_type' => Club::class,
        'attachable_id' => $club->id,
    ]);

    expect($club->attachments)->toHaveCount(1);
});

test('club logoUrl returns null when no logo', function () {
    $club = Club::factory()->create();

    expect($club->logoUrl)->toBeNull();
});
