<?php

use App\Enums\InvitationStatus;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;

test('invitation belongs to a club', function () {
    $invitation = ClubInvitation::factory()->create();
    expect($invitation->club)->toBeInstanceOf(Club::class);
});

test('invitation belongs to an inviter', function () {
    $invitation = ClubInvitation::factory()->create();
    expect($invitation->inviter)->toBeInstanceOf(User::class);
});

test('invitation casts status to enum', function () {
    $invitation = ClubInvitation::factory()->create();
    expect($invitation->status)->toBe(InvitationStatus::Pending);
});

test('pending scope returns only pending invitations', function () {
    ClubInvitation::factory()->create();
    ClubInvitation::factory()->accepted()->create();

    expect(ClubInvitation::query()->pending()->count())->toBe(1);
});

test('valid scope excludes expired invitations', function () {
    ClubInvitation::factory()->create();
    ClubInvitation::factory()->expired()->create();

    expect(ClubInvitation::query()->valid()->count())->toBe(1);
});
