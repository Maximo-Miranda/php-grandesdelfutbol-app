<?php

namespace App\Filament\Resources;

use App\Enums\VideoUploadStatus;
use App\Filament\Resources\MatchVideoUploadResource\Pages;
use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeQuotaService;
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
                    ->badge()
                    ->color(fn (MatchVideoUpload $record): string => match (true) {
                        $record->youtube_video_id !== null => 'success',
                        $record->error_message !== null => 'danger',
                        $record->youtube_upload_requested_at !== null => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (MatchVideoUpload $record): string => match (true) {
                        $record->youtube_video_id !== null => 'Subido',
                        $record->error_message !== null => 'Error',
                        $record->youtube_upload_requested_at !== null => 'Subiendo...',
                        default => "\u{2014}",
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
                        && $record->error_message === null
                    )
                    ->action(fn (MatchVideoUpload $record) => static::dispatchYouTubeUpload($record, 'Subida a YouTube iniciada')),
                Action::make('retry_youtube')
                    ->label('Reintentar YouTube')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MatchVideoUpload $record): bool => $record->best_resolution !== null
                        && $record->youtube_video_id === null
                        && $record->error_message !== null
                    )
                    ->action(fn (MatchVideoUpload $record) => static::dispatchYouTubeUpload($record, 'Reintento de subida a YouTube iniciado')),
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
                    ->label('Subir a YouTube')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $quotaService = app(YouTubeQuotaService::class);
                        $available = $quotaService->availableSlots();

                        if ($available <= 0) {
                            Notification::make()
                                ->title("Límite diario alcanzado ({$quotaService->quotaLabel()})")
                                ->body('Ningún video fue enviado. Intenta mañana.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $eligible = $records
                            ->filter(fn (MatchVideoUpload $record): bool => $record->best_resolution !== null
                                && $record->youtube_video_id === null
                                && $record->youtube_upload_requested_at === null
                            );

                        $toDispatch = $eligible->take($available);
                        $skipped = $eligible->count() - $toDispatch->count();

                        $toDispatch->each(function (MatchVideoUpload $record): void {
                            $record->update([
                                'youtube_upload_requested_at' => now(),
                                'error_message' => null,
                            ]);

                            UploadMatchToYouTube::dispatch($record);
                        });

                        $message = "{$toDispatch->count()} video(s) enviados a YouTube";

                        if ($skipped > 0) {
                            $message .= ". {$skipped} no pudieron agregarse por límite de cuota diaria.";
                        }

                        Notification::make()
                            ->title($message)
                            ->color($skipped > 0 ? 'warning' : 'success')
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

    private static function dispatchYouTubeUpload(MatchVideoUpload $record, string $successTitle): void
    {
        $quotaService = app(YouTubeQuotaService::class);

        if (! $quotaService->isQuotaAvailable()) {
            Notification::make()
                ->title("Límite diario de YouTube alcanzado ({$quotaService->quotaLabel()})")
                ->body('Intenta mañana cuando se renueve la cuota.')
                ->danger()
                ->send();

            return;
        }

        $record->update([
            'youtube_upload_requested_at' => now(),
            'error_message' => null,
        ]);

        UploadMatchToYouTube::dispatch($record);

        Notification::make()
            ->title($successTitle)
            ->success()
            ->send();
    }
}
