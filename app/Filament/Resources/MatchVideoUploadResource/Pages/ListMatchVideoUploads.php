<?php

namespace App\Filament\Resources\MatchVideoUploadResource\Pages;

use App\Filament\Resources\MatchVideoUploadResource;
use App\Filament\Widgets\YouTubeQuotaWidget;
use Filament\Resources\Pages\ListRecords;

class ListMatchVideoUploads extends ListRecords
{
    protected static string $resource = MatchVideoUploadResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            YouTubeQuotaWidget::class,
        ];
    }
}
