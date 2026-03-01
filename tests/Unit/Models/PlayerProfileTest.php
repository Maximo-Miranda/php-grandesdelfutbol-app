<?php

use App\Enums\Gender;
use App\Models\PlayerProfile;
use App\Models\User;

test('player profile belongs to a user', function () {
    $profile = PlayerProfile::factory()->create();
    expect($profile->user)->toBeInstanceOf(User::class);
});

test('player profile casts gender to enum', function () {
    $profile = PlayerProfile::factory()->create(['gender' => 'male']);
    expect($profile->gender)->toBe(Gender::Male);
});

test('player profile casts date_of_birth to date', function () {
    $profile = PlayerProfile::factory()->create(['date_of_birth' => '1990-01-15']);
    expect($profile->date_of_birth)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});
