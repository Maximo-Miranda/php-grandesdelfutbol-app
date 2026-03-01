<?php

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('club member belongs to a club', function () {
    $member = ClubMember::factory()->create();

    expect($member->club)->toBeInstanceOf(Club::class);
});

test('club member belongs to a user', function () {
    $member = ClubMember::factory()->create();

    expect($member->user)->toBeInstanceOf(User::class);
});

test('club member casts role to enum', function () {
    $member = ClubMember::factory()->create(['role' => ClubMemberRole::Admin]);

    expect($member->role)->toBe(ClubMemberRole::Admin);
});

test('club member casts status to enum', function () {
    $member = ClubMember::factory()->create(['status' => ClubMemberStatus::Pending]);

    expect($member->status)->toBe(ClubMemberStatus::Pending);
});

test('club member casts approved_at to datetime', function () {
    $member = ClubMember::factory()->create(['approved_at' => now()]);

    expect($member->approved_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('factory owner state sets role to owner', function () {
    $member = ClubMember::factory()->owner()->create();

    expect($member->role)->toBe(ClubMemberRole::Owner);
});

test('factory pending state sets status to pending', function () {
    $member = ClubMember::factory()->pending()->create();

    expect($member->status)->toBe(ClubMemberStatus::Pending)
        ->and($member->approved_at)->toBeNull();
});
