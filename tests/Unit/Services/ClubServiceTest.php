<?php

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\User;
use App\Services\ClubService;

test('createClub creates a club with owner as member', function () {
    $user = User::factory()->create();
    $service = new ClubService;

    $club = $service->createClub($user, [
        'name' => 'Test Club',
        'description' => 'A test club',
    ]);

    expect($club->name)->toBe('Test Club')
        ->and($club->description)->toBe('A test club')
        ->and($club->owner_id)->toBe($user->id)
        ->and($club->invite_token)->not->toBeNull()
        ->and($club->members)->toHaveCount(1);

    $member = $club->members->first();
    expect($member->user_id)->toBe($user->id)
        ->and($member->role)->toBe(ClubMemberRole::Owner)
        ->and($member->status)->toBe(ClubMemberStatus::Approved)
        ->and($member->approved_at)->not->toBeNull();
});

test('createClub sets requires_approval when specified', function () {
    $user = User::factory()->create();
    $service = new ClubService;

    $club = $service->createClub($user, [
        'name' => 'Approval Club',
        'requires_approval' => true,
    ]);

    expect($club->requires_approval)->toBeTrue();
});
