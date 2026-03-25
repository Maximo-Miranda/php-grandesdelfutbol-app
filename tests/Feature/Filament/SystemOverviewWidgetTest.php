<?php

use App\Filament\Widgets\SystemOverviewWidget;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('displays system overview stats', function (): void {
    Club::factory()->count(2)->create();
    Player::factory()->count(3)->create();

    Livewire::test(SystemOverviewWidget::class)
        ->assertOk();
});
