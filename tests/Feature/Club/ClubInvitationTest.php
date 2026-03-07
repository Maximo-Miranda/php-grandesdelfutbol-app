<?php

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\User;
use App\Notifications\ClubInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('admins can send invitations', function () {
    Notification::fake();
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.invitations.store', $club), ['email' => 'new@example.com'])
        ->assertRedirect();

    $this->assertDatabaseHas('club_invitations', [
        'club_id' => $club->id,
        'email' => 'new@example.com',
    ]);
});

test('regular members cannot send invitations', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);

    $this->actingAs($user)
        ->post(route('clubs.invitations.store', $club), ['email' => 'new@example.com'])
        ->assertForbidden();
});

test('invitation email is sent to existing users', function () {
    Notification::fake();
    $user = User::factory()->create();
    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('clubs.invitations.store', $club), ['email' => $user->email]);

    Notification::assertSentTo($user, ClubInvitationNotification::class);
});

test('invitation email is sent to non-existing users', function () {
    Notification::fake();
    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('clubs.invitations.store', $club), ['email' => 'newuser@example.com']);

    Notification::assertSentOnDemand(
        ClubInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'newuser@example.com',
    );
});

test('invitation landing page is accessible without auth', function () {
    $invitation = ClubInvitation::factory()->create();

    $this->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/AcceptInvitation')
            ->has('invitation.club')
            ->has('invitation.token')
            ->has('invitation.email')
        );
});

test('invitation landing page passes invitation email', function () {
    $invitation = ClubInvitation::factory()->create(['email' => 'invited@example.com']);

    $this->get(route('invitations.show', $invitation->token))
        ->assertInertia(fn ($page) => $page
            ->where('invitation.email', 'invited@example.com')
        );
});

test('logged in user is auto-accepted when visiting invitation link', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['email' => $user->email]);

    $this->actingAs($user)
        ->get(route('invitations.show', $invitation->token))
        ->assertRedirect(route('clubs.show', $invitation->club));

    $this->assertDatabaseHas('club_members', [
        'club_id' => $invitation->club_id,
        'user_id' => $user->id,
    ]);
});

test('logged in user visiting already-accepted invitation is redirected to club', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['email' => $user->email, 'status' => 'accepted']);

    $this->actingAs($user)
        ->get(route('invitations.show', $invitation->token))
        ->assertRedirect(route('clubs.show', $invitation->club));
});

test('authenticated users can accept via POST', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['email' => $user->email]);

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect();

    $this->assertDatabaseHas('club_members', [
        'club_id' => $invitation->club_id,
        'user_id' => $user->id,
    ]);
});

test('expired invitations show 404 on landing page', function () {
    $invitation = ClubInvitation::factory()->expired()->create();

    $this->get(route('invitations.show', $invitation->token))
        ->assertNotFound();
});

test('expired invitations cannot be accepted via POST', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->expired()->create();

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation->token))
        ->assertNotFound();
});
