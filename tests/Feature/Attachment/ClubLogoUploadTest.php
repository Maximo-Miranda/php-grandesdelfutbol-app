<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can upload a club logo', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('clubs.update', $club), [
            'name' => $club->name,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertRedirect();

    expect($club->attachments()->count())->toBe(1);
    Storage::disk('public')->assertExists($club->attachments()->first()->path);
});

test('logo upload validates file type', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('clubs.update', $club), [
            'name' => $club->name,
            'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('logo');
});

test('logo upload validates file size', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('clubs.update', $club), [
            'name' => $club->name,
            'logo' => UploadedFile::fake()->image('huge.png')->size(3000),
        ])
        ->assertSessionHasErrors('logo');
});
