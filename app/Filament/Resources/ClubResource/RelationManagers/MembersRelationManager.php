<?php

namespace App\Filament\Resources\ClubResource\RelationManagers;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'user.name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (ClubMemberRole $state): string => $state->label())
                    ->color(fn (ClubMemberRole $state): string => match ($state) {
                        ClubMemberRole::Owner => 'amber',
                        ClubMemberRole::Admin => 'blue',
                        ClubMemberRole::Player => 'emerald',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ClubMemberStatus $state): string => $state->label())
                    ->color(fn (ClubMemberStatus $state): string => match ($state) {
                        ClubMemberStatus::Approved => 'success',
                        ClubMemberStatus::Pending => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ClubMember $record): bool => $record->status === ClubMemberStatus::Pending)
                    ->action(function (ClubMember $record): void {
                        $record->update([
                            'status' => ClubMemberStatus::Approved,
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Miembro aprobado')
                            ->success()
                            ->send();
                    }),
                Action::make('make_admin')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (ClubMember $record): bool => $record->role === ClubMemberRole::Player
                        && $record->status === ClubMemberStatus::Approved
                    )
                    ->action(function (ClubMember $record): void {
                        $record->update(['role' => ClubMemberRole::Admin]);

                        Notification::make()
                            ->title('Rol actualizado a Admin')
                            ->success()
                            ->send();
                    }),
                Action::make('make_player')
                    ->icon('heroicon-o-user')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ClubMember $record): bool => $record->role === ClubMemberRole::Admin)
                    ->action(function (ClubMember $record): void {
                        $record->update(['role' => ClubMemberRole::Player]);

                        Notification::make()
                            ->title('Rol actualizado a Jugador')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
