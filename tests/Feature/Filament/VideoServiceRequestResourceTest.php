<?php

use App\Filament\Resources\VideoServiceRequestResource\Pages\ListVideoServiceRequests;
use App\Models\User;
use App\Models\VideoServiceRequest;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('can load the list page', function (): void {
    $requests = VideoServiceRequest::factory()->count(3)->create();

    Livewire::test(ListVideoServiceRequests::class)
        ->assertOk()
        ->assertCanSeeTableRecords($requests);
});

it('can search by name', function (): void {
    $target = VideoServiceRequest::factory()->create(['name' => 'Juan Pérez']);
    $other = VideoServiceRequest::factory()->create(['name' => 'María López']);

    Livewire::test(ListVideoServiceRequests::class)
        ->searchTable('Juan Pérez')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can search by club name', function (): void {
    $target = VideoServiceRequest::factory()->create(['club_name' => 'FC Único']);
    $other = VideoServiceRequest::factory()->create(['club_name' => 'Club Diferente']);

    Livewire::test(ListVideoServiceRequests::class)
        ->searchTable('FC Único')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can filter by status', function (): void {
    $pending = VideoServiceRequest::factory()->create();
    $completed = VideoServiceRequest::factory()->completed()->create();

    Livewire::test(ListVideoServiceRequests::class)
        ->filterTable('status', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$completed]);
});

it('can sort by created_at', function (): void {
    $older = VideoServiceRequest::factory()->create(['created_at' => now()->subDay()]);
    $newer = VideoServiceRequest::factory()->create(['created_at' => now()]);

    Livewire::test(ListVideoServiceRequests::class)
        ->sortTable('created_at', 'asc')
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true);
});
