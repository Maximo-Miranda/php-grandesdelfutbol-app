<?php

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\InvitationStatus;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Notification;

test('sendInvitation creates an invitation', function () {
    Notification::fake();
    $club = Club::factory()->create();
    $inviter = User::factory()->create();
    $service = new InvitationService;

    $invitation = $service->sendInvitation($club, 'test@example.com', $inviter);

    expect($invitation->club_id)->toBe($club->id)
        ->and($invitation->email)->toBe('test@example.com')
        ->and($invitation->status)->toBe(InvitationStatus::Pending)
        ->and($invitation->invited_by)->toBe($inviter->id);
});

test('acceptInvitation marks invitation as accepted and creates member', function () {
    $invitation = ClubInvitation::factory()->create();
    $user = User::factory()->create();
    $service = new InvitationService;

    $member = $service->acceptInvitation($invitation, $user);

    $invitation->refresh();
    expect($invitation->status)->toBe(InvitationStatus::Accepted)
        ->and($member->club_id)->toBe($invitation->club_id)
        ->and($member->user_id)->toBe($user->id)
        ->and($member->role)->toBe(ClubMemberRole::Player)
        ->and($member->status)->toBe(ClubMemberStatus::Approved);
});

test('joinViaLink creates approved member when no approval required', function () {
    $club = Club::factory()->create(['requires_approval' => false]);
    $user = User::factory()->create();
    $service = new InvitationService;

    $member = $service->joinViaLink($club, $user);

    expect($member->status)->toBe(ClubMemberStatus::Approved)
        ->and($member->approved_at)->not->toBeNull();
});

test('joinViaLink creates pending member when approval required', function () {
    $club = Club::factory()->create(['requires_approval' => true]);
    $user = User::factory()->create();
    $service = new InvitationService;

    $member = $service->joinViaLink($club, $user);

    expect($member->status)->toBe(ClubMemberStatus::Pending)
        ->and($member->approved_at)->toBeNull();
});

test('joinViaLink does not duplicate members', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $service = new InvitationService;

    $service->joinViaLink($club, $user);

    expect(ClubMember::query()->where('club_id', $club->id)->where('user_id', $user->id)->count())->toBe(1);
});
