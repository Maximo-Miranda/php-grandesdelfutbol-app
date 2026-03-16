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
        ->and($club->slug)->toBe('test-club')
        ->and($club->requires_approval)->toBeTrue()
        ->and($club->is_invite_active)->toBeTrue()
        ->and($club->members)->toHaveCount(1);

    $member = $club->members->first();
    expect($member->user_id)->toBe($user->id)
        ->and($member->role)->toBe(ClubMemberRole::Owner)
        ->and($member->status)->toBe(ClubMemberStatus::Approved)
        ->and($member->approved_at)->not->toBeNull();
});

test('createClub generates unique slug for duplicate names', function () {
    $user = User::factory()->create();
    $service = new ClubService;

    $club1 = $service->createClub($user, ['name' => 'Los Cracks']);
    $club2 = $service->createClub($user, ['name' => 'Los Cracks']);

    expect($club1->slug)->toBe('los-cracks')
        ->and($club2->slug)->toBe('los-cracks-1');
});
