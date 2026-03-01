<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('user can upload profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'nickname' => 'TestPlayer',
            'photo' => UploadedFile::fake()->image('photo.jpg', 300, 300),
        ])
        ->assertRedirect();

    $profile = $user->playerProfile;
    expect($profile)->not->toBeNull()
        ->and($profile->attachments()->count())->toBe(1);

    Storage::disk('public')->assertExists($profile->attachments()->first()->path);
});

test('photo upload validates file type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'photo' => UploadedFile::fake()->create('doc.txt', 100, 'text/plain'),
        ])
        ->assertSessionHasErrors('photo');
});
