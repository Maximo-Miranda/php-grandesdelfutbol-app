<?php

use App\Models\ClubInvitation;

test('new user registration with valid invite token joins the club', function () {
    $invitation = ClubInvitation::factory()->create([
        'email' => 'newuser@example.com',
    ]);

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invite_token' => $invitation->token,
    ])->assertRedirect();

    $this->assertDatabaseHas('club_members', [
        'club_id' => $invitation->club_id,
    ]);

    $invitation->refresh();
    expect($invitation->status->value)->toBe('accepted');
});

test('registration without invite token works normally', function () {
    $this->post('/register', [
        'name' => 'Normal User',
        'email' => 'normal@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'normal@example.com']);
    $this->assertDatabaseCount('club_members', 0);
});
