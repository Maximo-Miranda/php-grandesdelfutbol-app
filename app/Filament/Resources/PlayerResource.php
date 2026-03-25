<?php

namespace App\Filament\Resources;

use App\Enums\PlayerPosition;
use App\Filament\Resources\PlayerResource\Pages;
use App\Models\Player;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static bool $shouldSkipAuthorization = true;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Jugadores';

    protected static string|UnitEnum|null $navigationGroup = 'Clubs';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('club.name')
                    ->label('Club')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position')
                    ->badge()
                    ->formatStateUsing(fn (PlayerPosition $state): string => $state->label()),
                TextColumn::make('goals')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('assists')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('club')
                    ->relationship('club', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
        ];
    }
}
