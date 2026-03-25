<?php

namespace App\Filament\Resources;

use App\Enums\VideoServiceRequestStatus;
use App\Filament\Resources\VideoServiceRequestResource\Pages;
use App\Models\VideoServiceRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VideoServiceRequestResource extends Resource
{
    protected static ?string $model = VideoServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'Solicitudes';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('phone')->label('Teléfono / WhatsApp')->copyable(),
                        TextEntry::make('user.name')->label('Usuario registrado')->placeholder('No registrado'),
                    ]),
                Section::make('Servicio')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('selected_plan')->label('Plan')->badge(),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (VideoServiceRequestStatus $state): string => match ($state) {
                                VideoServiceRequestStatus::Pending => 'warning',
                                VideoServiceRequestStatus::Contacted => 'info',
                                VideoServiceRequestStatus::Completed => 'success',
                                VideoServiceRequestStatus::Rejected => 'danger',
                            })
                            ->formatStateUsing(fn (VideoServiceRequestStatus $state): string => $state->label()),
                        TextEntry::make('venue_address')->label('Dirección de la cancha')->columnSpanFull(),
                        TextEntry::make('preferred_date')->label('Fecha')->date(),
                        TextEntry::make('preferred_time')->label('Hora'),
                        TextEntry::make('club_name')->label('Club'),
                        TextEntry::make('created_at')->label('Solicitado')->dateTime(),
                    ]),
                Section::make('Mensaje')
                    ->schema([
                        TextEntry::make('message')->label('')->placeholder('Sin mensaje.'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('club_name')
                    ->label('Club')
                    ->searchable(),
                TextColumn::make('selected_plan')
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (VideoServiceRequestStatus $state): string => match ($state) {
                        VideoServiceRequestStatus::Pending => 'warning',
                        VideoServiceRequestStatus::Contacted => 'info',
                        VideoServiceRequestStatus::Completed => 'success',
                        VideoServiceRequestStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (VideoServiceRequestStatus $state): string => $state->label()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        VideoServiceRequestStatus::Pending->value => VideoServiceRequestStatus::Pending->label(),
                        VideoServiceRequestStatus::Contacted->value => VideoServiceRequestStatus::Contacted->label(),
                        VideoServiceRequestStatus::Completed->value => VideoServiceRequestStatus::Completed->label(),
                        VideoServiceRequestStatus::Rejected->value => VideoServiceRequestStatus::Rejected->label(),
                    ]),
                SelectFilter::make('selected_plan')
                    ->options(fn (): array => VideoServiceRequest::query()
                        ->whereNotNull('selected_plan')
                        ->distinct()
                        ->pluck('selected_plan', 'selected_plan')
                        ->toArray()
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('mark_contacted')
                    ->label('Contactado')
                    ->icon('heroicon-o-phone')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (VideoServiceRequest $record): bool => $record->status === VideoServiceRequestStatus::Pending)
                    ->action(function (VideoServiceRequest $record): void {
                        $record->update(['status' => VideoServiceRequestStatus::Contacted]);

                        Notification::make()
                            ->title('Solicitud marcada como contactada')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_completed')
                    ->label('Completado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (VideoServiceRequest $record): void {
                        $record->update(['status' => VideoServiceRequestStatus::Completed]);

                        Notification::make()
                            ->title('Solicitud marcada como completada')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_rejected')
                    ->label('Rechazado')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VideoServiceRequest $record): void {
                        $record->update(['status' => VideoServiceRequestStatus::Rejected]);

                        Notification::make()
                            ->title('Solicitud marcada como rechazada')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideoServiceRequests::route('/'),
            'view' => Pages\ViewVideoServiceRequest::route('/{record}'),
        ];
    }
}
