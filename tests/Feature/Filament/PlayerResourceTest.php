<?php

use App\Filament\Resources\PlayerResource\Pages\ListPlayers;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('can load the list page', function (): void {
    $players = Player::factory()->count(3)->create();

    Livewire::test(ListPlayers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($players);
});

it('can search by player name', function (): void {
    $target = Player::factory()->create(['name' => 'Lionel Messi']);
    $other = Player::factory()->create(['name' => 'Cristiano Ronaldo']);

    Livewire::test(ListPlayers::class)
        ->searchTable('Lionel Messi')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can filter by club', function (): void {
    $clubA = Club::factory()->create(['name' => 'Club Alpha']);
    $clubB = Club::factory()->create(['name' => 'Club Beta']);
    $playerA = Player::factory()->create(['club_id' => $clubA->id]);
    $playerB = Player::factory()->create(['club_id' => $clubB->id]);

    Livewire::test(ListPlayers::class)
        ->filterTable('club', $clubA->id)
        ->assertCanSeeTableRecords([$playerA])
        ->assertCanNotSeeTableRecords([$playerB]);
});

it('can filter by active status', function (): void {
    $active = Player::factory()->create();
    $inactive = Player::factory()->inactive()->create();

    Livewire::test(ListPlayers::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('can sort by goals', function (): void {
    $low = Player::factory()->create(['goals' => 2]);
    $high = Player::factory()->create(['goals' => 20]);

    Livewire::test(ListPlayers::class)
        ->sortTable('goals', 'desc')
        ->assertCanSeeTableRecords([$high, $low], inOrder: true);
});

it('shows players from all clubs regardless of global scope', function (): void {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    $playerA = Player::factory()->create(['club_id' => $clubA->id]);
    $playerB = Player::factory()->create(['club_id' => $clubB->id]);

    Livewire::test(ListPlayers::class)
        ->assertCanSeeTableRecords([$playerA, $playerB]);
});
