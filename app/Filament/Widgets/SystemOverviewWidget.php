<?php

namespace App\Filament\Widgets;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Clubs', Club::count())
                ->description('Total de clubs registrados')
                ->icon('heroicon-o-building-office-2'),
            Stat::make('Usuarios', User::count())
                ->description('Total de usuarios registrados')
                ->icon('heroicon-o-user-group'),
            Stat::make('Partidos', FootballMatch::withoutGlobalScopes()->count())
                ->description('Total de partidos jugados')
                ->icon('heroicon-o-trophy'),
            Stat::make('Jugadores', Player::withoutGlobalScopes()->count())
                ->description('Total de jugadores registrados')
                ->icon('heroicon-o-users'),
        ];
    }
}
