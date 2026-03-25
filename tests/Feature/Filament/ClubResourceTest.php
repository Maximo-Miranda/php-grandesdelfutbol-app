<?php

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Filament\Resources\ClubResource\Pages\ListClubs;
use App\Filament\Resources\ClubResource\Pages\ViewClub;
use App\Filament\Resources\ClubResource\RelationManagers\MatchesRelationManager;
use App\Filament\Resources\ClubResource\RelationManagers\MembersRelationManager;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('can load the list page', function (): void {
    $clubs = Club::factory()->count(3)->create();

    Livewire::test(ListClubs::class)
        ->assertOk()
        ->assertCanSeeTableRecords($clubs);
});

it('can search by club name', function (): void {
    $target = Club::factory()->create(['name' => 'FC Barcelona']);
    $other = Club::factory()->create(['name' => 'Real Madrid']);

    Livewire::test(ListClubs::class)
        ->searchTable('FC Barcelona')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can search by owner name', function (): void {
    $owner = User::factory()->create(['name' => 'John Owner']);
    $target = Club::factory()->create(['owner_id' => $owner->id]);
    $other = Club::factory()->create();

    Livewire::test(ListClubs::class)
        ->searchTable('John Owner')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can sort by name', function (): void {
    $alpha = Club::factory()->create(['name' => 'Alpha FC']);
    $zeta = Club::factory()->create(['name' => 'Zeta United']);

    Livewire::test(ListClubs::class)
        ->sortTable('name', 'asc')
        ->assertCanSeeTableRecords([$alpha, $zeta], inOrder: true);
});

it('can load the view page with relation managers', function (): void {
    $user = auth()->user();
    $club = Club::factory()->create(['owner_id' => $user->id]);
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
        'role' => ClubMemberRole::Owner,
        'status' => ClubMemberStatus::Approved,
    ]);

    Livewire::test(ViewClub::class, ['record' => $club->getRouteKey()])
        ->assertOk();
});

it('displays members in the relation manager', function (): void {
    $club = Club::factory()->create();
    $member = ClubMember::factory()->create([
        'club_id' => $club->id,
        'role' => ClubMemberRole::Player,
        'status' => ClubMemberStatus::Approved,
    ]);

    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => ViewClub::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$member]);
});

it('can approve a pending member', function (): void {
    $club = Club::factory()->create();
    $member = ClubMember::factory()->create([
        'club_id' => $club->id,
        'role' => ClubMemberRole::Player,
        'status' => ClubMemberStatus::Pending,
    ]);

    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => ViewClub::class,
    ])
        ->callTableAction('approve', $member);

    expect($member->fresh()->status)->toBe(ClubMemberStatus::Approved);
});

it('can promote a player to admin', function (): void {
    $club = Club::factory()->create();
    $member = ClubMember::factory()->create([
        'club_id' => $club->id,
        'role' => ClubMemberRole::Player,
        'status' => ClubMemberStatus::Approved,
    ]);

    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => ViewClub::class,
    ])
        ->callTableAction('make_admin', $member);

    expect($member->fresh()->role)->toBe(ClubMemberRole::Admin);
});

it('can demote an admin to player', function (): void {
    $club = Club::factory()->create();
    $member = ClubMember::factory()->create([
        'club_id' => $club->id,
        'role' => ClubMemberRole::Admin,
        'status' => ClubMemberStatus::Approved,
    ]);

    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => ViewClub::class,
    ])
        ->callTableAction('make_player', $member);

    expect($member->fresh()->role)->toBe(ClubMemberRole::Player);
});

it('displays matches in the relation manager', function (): void {
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    Livewire::test(MatchesRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => ViewClub::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$match]);
});
