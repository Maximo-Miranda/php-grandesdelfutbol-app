<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use App\Notifications\MemberApprovedNotification;
use App\Notifications\NewMemberRequestNotification;
use Illuminate\Support\Facades\Notification;

test('admins are notified when a new member requests to join', function () {
    Notification::fake();

    $club = Club::factory()->withInviteActive()->withApproval()->create();
    $owner = $club->owner;
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $joiner = User::factory()->create();

    $this->actingAs($joiner)
        ->post(route('clubs.join.store', $club->invite_token))
        ->assertRedirect();

    Notification::assertSentTo($owner, NewMemberRequestNotification::class);
    Notification::assertSentTo($admin, NewMemberRequestNotification::class);
});

test('admins are not notified when member is auto-approved', function () {
    Notification::fake();

    $club = Club::factory()->withInviteActive()->create(['requires_approval' => false]);
    $owner = $club->owner;
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);

    $joiner = User::factory()->create();

    $this->actingAs($joiner)
        ->post(route('clubs.join.store', $club->invite_token))
        ->assertRedirect();

    Notification::assertNotSentTo($owner, NewMemberRequestNotification::class);
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
