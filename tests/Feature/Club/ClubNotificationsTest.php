<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Support\Facades\Http;
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
            ->has('ntfyTopic')
            ->has('ntfyUrl')
            ->has('ntfyHost')
            ->has('vapidPublicKey')
            ->where('ntfyTopic', "gdf-{$club->ulid}"),
        );
});

test('admin can send test notification to club ntfy topic', function () {
    Http::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('clubs.notifications.test', $club))
        ->assertRedirect()
        ->assertSessionHas('success', 'Notificación de prueba enviada al canal del club.');

    Http::assertSentCount(1);
});

test('non-admin member cannot send test notification', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.notifications.test', $club))
        ->assertForbidden();
});
