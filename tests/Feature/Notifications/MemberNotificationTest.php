<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use App\Notifications\MemberApprovedNotification;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MemberRemovedNotification;
use App\Notifications\NewMemberRequestNotification;
use Illuminate\Support\Facades\Notification;

test('admins are notified when a new member requests to join', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $owner = $club->owner;
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $joiner = User::factory()->create();

    $this->actingAs($joiner)
        ->post(route('clubs.join.store', $club->slug))
        ->assertRedirect();

    Notification::assertSentTo($owner, NewMemberRequestNotification::class);
    Notification::assertSentTo($admin, NewMemberRequestNotification::class);
});

test('user is notified when their membership is approved', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $pendingUser = User::factory()->create();
    $pendingMember = ClubMember::factory()->pending()->create([
        'club_id' => $club->id,
        'user_id' => $pendingUser->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('clubs.members.approve', [$club, $pendingMember]))
        ->assertRedirect();

    Notification::assertSentTo($pendingUser, MemberApprovedNotification::class);
});

test('new member request notification contains correct content', function () {
    $club = Club::factory()->create();
    $requester = User::factory()->create(['name' => 'Carlos']);

    $notification = new NewMemberRequestNotification($club, $requester);
    $mail = $notification->toMail($club->owner);

    expect($mail->subject)->toBe("Nueva solicitud en {$club->name}")
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}/members");
});

test('member approved notification contains correct content', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['name' => 'Maria']);

    $notification = new MemberApprovedNotification($club);
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe("Bienvenido a {$club->name}!")
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}");
});

test('admins are notified when a member leaves the club', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $owner = $club->owner;
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);

    $player = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $player->id]);

    $this->actingAs($player)
        ->post(route('clubs.leave', $club))
        ->assertRedirect(route('clubs.index'));

    Notification::assertSentTo($owner, MemberLeftNotification::class);
});

test('member is notified when removed from the club', function () {
    Notification::fake();

    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $player = User::factory()->create();
    $membership = ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $player->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.remove', [$club, $membership]))
        ->assertRedirect();

    Notification::assertSentTo($player, MemberRemovedNotification::class);
});

test('member left notification contains correct content', function () {
    $club = Club::factory()->create();
    $member = User::factory()->create(['name' => 'Pedro']);
    $admin = User::factory()->create(['name' => 'Admin']);

    $notification = new MemberLeftNotification($club, $member);
    $mail = $notification->toMail($admin);

    expect($mail->subject)->toBe("Pedro salió de {$club->name}")
        ->and($mail->actionUrl)->toContain("/clubs/{$club->ulid}/members");
});

test('member removed notification contains correct content', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['name' => 'Juan']);

    $notification = new MemberRemovedNotification($club);
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe("Has sido removido de {$club->name}");
});
