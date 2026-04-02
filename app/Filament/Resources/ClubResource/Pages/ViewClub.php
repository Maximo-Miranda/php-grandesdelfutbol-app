<?php

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use App\Services\PlayerImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\File;

class ViewClub extends ViewRecord
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_players')
                ->label('Importar Jugadores')
                ->icon('heroicon-o-arrow-up-tray')
                ->slideOver()
                ->schema([
                    FileUpload::make('csv_file')
                        ->label('Archivo CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->helperText('Columnas esperadas: nombre, email (opcional), posicion (opcional)'),
                ])
                ->action(function (array $data, PlayerImportService $service): void {
                    $filePath = storage_path('app/public/'.$data['csv_file']);

                    $result = $service->importFromCsv($this->record, $filePath);

                    File::delete($filePath);

                    if ($result['errors'] !== []) {
                        Notification::make()
                            ->title($result['errors'][0])
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title("{$result['imported']} jugador(es) importado(s) exitosamente")
                        ->success()
                        ->send();
                }),
        ];
    }
}
