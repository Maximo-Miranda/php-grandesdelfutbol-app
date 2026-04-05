<?php

namespace App\Filament\Resources\NewsDictionaryEntries\Pages;

use App\Filament\Resources\NewsDictionaryEntries\NewsDictionaryEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsDictionaryEntries extends ManageRecords
{
    protected static string $resource = NewsDictionaryEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
