<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('authenticated users can view their player profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('player-profile.edit'))
        ->assertOk();
});

test('authenticated users can update their player profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'nickname' => 'TestNick',
            'preferred_position' => 'CM',
            'nationality' => 'Argentina',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('player_profiles', [
        'user_id' => $user->id,
        'nickname' => 'TestNick',
        'preferred_position' => 'CM',
    ]);
});

test('player profile can be updated multiple times', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), ['nickname' => 'First'])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), ['nickname' => 'Second'])
        ->assertRedirect();

    $this->assertDatabaseHas('player_profiles', [
        'user_id' => $user->id,
        'nickname' => 'Second',
    ]);
    $this->assertDatabaseCount('player_profiles', 1);
});

test('player can upload a profile photo', function () {
    Storage::fake(config('media-library.disk_name'));

    $user = User::factory()->create();
    $photo = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'nickname' => 'PhotoNick',
            'photo' => $photo,
        ])
        ->assertRedirect();

    $profile = $user->fresh()->playerProfile;
    expect($profile->getFirstMedia('photo'))->not->toBeNull();
    expect($profile->photo_url)->not->toBeNull();
});

test('uploading a new photo replaces the previous one', function () {
    Storage::fake(config('media-library.disk_name'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'photo' => UploadedFile::fake()->image('first.jpg', 400, 400),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'photo' => UploadedFile::fake()->image('second.jpg', 400, 400),
        ])
        ->assertRedirect();

    $profile = $user->fresh()->playerProfile;
    expect($profile->getMedia('photo'))->toHaveCount(1);
});

test('photo upload rejects files that are too small in dimensions', function () {
    Storage::fake(config('media-library.disk_name'));

    $user = User::factory()->create();
    $photo = UploadedFile::fake()->image('tiny.jpg', 100, 100);

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'photo' => $photo,
        ])
        ->assertSessionHasErrors('photo');
});

test('photo upload rejects non-image files', function () {
    Storage::fake(config('media-library.disk_name'));

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'photo' => $file,
        ])
        ->assertSessionHasErrors('photo');
});

test('photo upload rejects files exceeding max size', function () {
    Storage::fake(config('media-library.disk_name'));

    $user = User::factory()->create();
    $photo = UploadedFile::fake()->image('huge.jpg', 400, 400)->size(11 * 1024);

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'photo' => $photo,
        ])
        ->assertSessionHasErrors('photo');
});

test('profile can be updated without uploading a photo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('player-profile.update'), [
            'nickname' => 'NoPhoto',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('player_profiles', [
        'user_id' => $user->id,
        'nickname' => 'NoPhoto',
    ]);
});
