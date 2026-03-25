<?php

use App\Models\Club;
use App\Models\User;
use App\Services\ClubContext;

beforeEach(function () {
    config(['app.super_admin_emails' => ['admin@test.com']]);
});

test('super admin bypasses EnsureClubMember middleware', function () {
    $superAdmin = User::factory()->create(['email' => 'admin@test.com']);
    $club = Club::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('clubs.show', $club))
        ->assertOk();
});

test('super admin bypasses EnsureClubAdmin middleware', function () {
    $superAdmin = User::factory()->create(['email' => 'admin@test.com']);
    $club = Club::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('clubs.edit', $club))
        ->assertOk();
});

test('super admin without clubs sets club context from route parameter', function () {
    $superAdmin = User::factory()->create(['email' => 'admin@test.com']);
    $club = Club::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('clubs.show', $club))
        ->assertOk();

    expect(app(ClubContext::class)->id())->toBe($club->id);
});

test('regular user without membership is forbidden from club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertForbidden();
});

test('regular user without clubs is redirected to club creation from non-independent route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertRedirect(route('clubs.create'));
});
