<?php

use App\Filament\Resources\MatchVideoUploadResource\Pages\ListMatchVideoUploads;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('can load the list page', function (): void {
    $uploads = MatchVideoUpload::factory()->count(3)->create();

    Livewire::test(ListMatchVideoUploads::class)
        ->assertOk()
        ->assertCanSeeTableRecords($uploads);
});

it('can search by match title', function (): void {
    $target = MatchVideoUpload::factory()->create();
    $other = MatchVideoUpload::factory()->create();

    Livewire::test(ListMatchVideoUploads::class)
        ->searchTable($target->match->title)
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can filter by status', function (): void {
    $ready = MatchVideoUpload::factory()->ready()->create();
    $failed = MatchVideoUpload::factory()->failed()->create();

    Livewire::test(ListMatchVideoUploads::class)
        ->filterTable('status', 'ready')
        ->assertCanSeeTableRecords([$ready])
        ->assertCanNotSeeTableRecords([$failed]);
});

it('can sort by created_at', function (): void {
    $older = MatchVideoUpload::factory()->create(['created_at' => now()->subDay()]);
    $newer = MatchVideoUpload::factory()->create(['created_at' => now()]);

    Livewire::test(ListMatchVideoUploads::class)
        ->sortTable('created_at', 'asc')
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true);
});
