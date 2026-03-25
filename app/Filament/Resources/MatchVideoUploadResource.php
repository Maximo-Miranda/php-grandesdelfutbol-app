<?php

namespace App\Filament\Resources;

use App\Enums\VideoUploadStatus;
use App\Filament\Resources\MatchVideoUploadResource\Pages;
use App\Models\MatchVideoUpload;
use App\Support\YouTubeUrlParser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class MatchVideoUploadResource extends Resource
{
    protected static ?string $model = MatchVideoUpload::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Videos';

    protected static string|UnitEnum|null $navigationGroup = 'YouTube';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('match.title')
                    ->searchable(),
                TextColumn::make('match.club.name')
                    ->label('Club')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (VideoUploadStatus $state): string => match ($state) {
                        VideoUploadStatus::Ready => 'success',
                        VideoUploadStatus::Encoding => 'warning',
                        VideoUploadStatus::Failed => 'danger',
                        VideoUploadStatus::Uploading => 'info',
                    }),
                TextColumn::make('best_resolution')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('youtube_video_id')
                    ->label('YouTube')
                    ->formatStateUsing(function (MatchVideoUpload $record): string {
                        if ($record->youtube_video_id) {
                            return 'Subido';
                        }

                        if ($record->youtube_upload_requested_at) {
                            return 'Pendiente';
                        }

                        return "\u{2014}";
                    }),
                TextColumn::make('encoded_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('original_filename')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        VideoUploadStatus::Uploading->value => VideoUploadStatus::Uploading->label(),
                        VideoUploadStatus::Encoding->value => VideoUploadStatus::Encoding->label(),
                        VideoUploadStatus::Ready->value => VideoUploadStatus::Ready->label(),
                        VideoUploadStatus::Failed->value => VideoUploadStatus::Failed->label(),
                    ]),
                TernaryFilter::make('YouTube')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('youtube_video_id'),
                        false: fn (Builder $query) => $query->whereNull('youtube_video_id'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('upload_to_youtube')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (MatchVideoUpload $record): bool => $record->best_resolution !== null
                        && $record->youtube_video_id === null
                        && $record->youtube_upload_requested_at === null
                    )
                    ->action(function (MatchVideoUpload $record): void {
                        $record->update(['youtube_upload_requested_at' => now()]);

                        Notification::make()
                            ->title('Subida a YouTube solicitada')
                            ->success()
                            ->send();
                    }),
                Action::make('link_youtube')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn (MatchVideoUpload $record): bool => $record->youtube_video_id === null)
                    ->slideOver()
                    ->schema([
                        TextInput::make('youtube_url')
                            ->label('YouTube URL')
                            ->required()
                            ->url(),
                    ])
                    ->action(function (MatchVideoUpload $record, array $data): void {
                        $videoId = YouTubeUrlParser::extractVideoId($data['youtube_url']);

                        if ($videoId === null) {
                            Notification::make()
                                ->title('URL de YouTube no valida')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'youtube_video_id' => $videoId,
                            'youtube_uploaded_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Video de YouTube vinculado')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('queue_youtube')
                    ->label('Cola YouTube')
                    ->icon('heroicon-o-queue-list')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $updated = $records
                            ->filter(fn (MatchVideoUpload $record): bool => $record->best_resolution !== null
                                && $record->youtube_video_id === null
                                && $record->youtube_upload_requested_at === null
                            )
                            ->each(fn (MatchVideoUpload $record) => $record->update([
                                'youtube_upload_requested_at' => now(),
                            ]));

                        Notification::make()
                            ->title("{$updated->count()} video(s) agregados a la cola de YouTube")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatchVideoUploads::route('/'),
        ];
    }
}
