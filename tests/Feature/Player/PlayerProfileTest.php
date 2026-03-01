<?php

use App\Models\User;

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
