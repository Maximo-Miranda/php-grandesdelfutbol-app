<?php

namespace App\Filament\Widgets;

use App\Models\MatchVideoUpload;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class YouTubeQuotaWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->format('Y-m-d');
        $dailyUploads = (int) Cache::get("youtube-daily-uploads:{$today}", 0);
        $dailyLimit = config('youtube.daily_upload_limit', 6);

        $pendingUploads = MatchVideoUpload::query()
            ->whereNotNull('youtube_upload_requested_at')
            ->whereNull('youtube_video_id')
            ->count();

        $totalEncoded = MatchVideoUpload::query()
            ->whereNotNull('best_resolution')
            ->count();

        return [
            Stat::make('Subidas hoy', "{$dailyUploads} / {$dailyLimit}")
                ->description('Cuota diaria de YouTube')
                ->color($dailyUploads >= $dailyLimit ? 'danger' : 'success'),
            Stat::make('Pendientes', $pendingUploads)
                ->description('Esperando subida a YouTube')
                ->color($pendingUploads > 0 ? 'warning' : 'success'),
            Stat::make('Videos codificados', $totalEncoded)
                ->description('Total con resolucion lista'),
        ];
    }
}
