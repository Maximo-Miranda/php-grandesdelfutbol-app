<?php

use App\Enums\AttachmentCollection;
use App\Models\Attachment;
use App\Models\Club;
use App\Services\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('upload creates attachment record', function () {
    Storage::fake('public');
    $club = Club::factory()->create();
    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    $service = new AttachmentService;
    $attachment = $service->upload($club, $file, AttachmentCollection::Logo);

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->collection)->toBe(AttachmentCollection::Logo)
        ->and($attachment->original_name)->toBe('logo.png')
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->size)->toBeGreaterThan(0);

    Storage::disk('public')->assertExists($attachment->path);
});

test('upload replaces existing attachment for same collection', function () {
    Storage::fake('public');
    $club = Club::factory()->create();

    $service = new AttachmentService;
    $first = $service->upload($club, UploadedFile::fake()->image('old.png'), AttachmentCollection::Logo);
    $second = $service->upload($club, UploadedFile::fake()->image('new.png'), AttachmentCollection::Logo);

    expect($club->attachments()->count())->toBe(1)
        ->and($second->original_name)->toBe('new.png');

    Storage::disk('public')->assertMissing($first->path);
    Storage::disk('public')->assertExists($second->path);
});

test('delete removes file and record', function () {
    Storage::fake('public');
    $club = Club::factory()->create();

    $service = new AttachmentService;
    $attachment = $service->upload($club, UploadedFile::fake()->image('logo.png'), AttachmentCollection::Logo);
    $path = $attachment->path;

    $service->delete($attachment);

    Storage::disk('public')->assertMissing($path);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});
