<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access club notifications page', function () {
    $club = Club::factory()->create();

    $this->get(route('clubs.notifications.show', $club))->assertRedirect(route('login'));
});

test('club member can view notifications page', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.notifications.show', $club))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clubs/Notifications')
            ->has('club')
            ->has('vapidPublicKey'),
        );
});

test('member with push subscription can send test notification', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $user->updatePushSubscription('https://push.example.com/1', 'key1', 'auth1');

    $this->actingAs($user)
        ->post(route('clubs.notifications.test', $club))
        ->assertRedirect()
        ->assertSessionHas('success', 'Notificación de prueba enviada.');
});

test('member without push subscription cannot send test notification', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.notifications.test', $club))
        ->assertRedirect()
        ->assertSessionHas('error', 'Primero activa las notificaciones push.');
});
