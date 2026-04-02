<?php

namespace App\Filament\Widgets;

use App\Models\MatchVideoUpload;
use App\Services\GoogleAuthService;
use App\Services\GoogleDriveService;
use App\Services\YouTubeQuotaService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class YouTubeQuotaWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $quotaService = app(YouTubeQuotaService::class);
        $authService = app(GoogleAuthService::class);
        $isConfigured = $authService->isConfigured();

        $inQueue = MatchVideoUpload::query()
            ->whereNotNull('youtube_upload_requested_at')
            ->whereNull('youtube_video_id')
            ->whereNull('error_message')
            ->count();

        $failed = MatchVideoUpload::query()
            ->whereNotNull('error_message')
            ->whereNull('youtube_video_id')
            ->count();

        $totalEncoded = MatchVideoUpload::query()
            ->whereNotNull('best_resolution')
            ->count();

        $authUrl = route('youtube.authorize');

        $stats = [
            Stat::make('Google OAuth', $isConfigured ? 'Conectado' : 'No conectado')
                ->description($isConfigured ? 'Click para re-autorizar' : 'Click para conectar cuenta')
                ->descriptionIcon($isConfigured ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($isConfigured ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'onclick' => "window.location.href='{$authUrl}'",
                ]),
            $this->driveStorageStat($isConfigured),
            Stat::make('Subidas hoy', $quotaService->quotaLabel())
                ->description('Cuota diaria de YouTube')
                ->color($quotaService->isQuotaAvailable() ? 'success' : 'danger'),
            Stat::make('En cola', $inQueue)
                ->description('Subiendo a YouTube')
                ->color($inQueue > 0 ? 'warning' : 'success'),
            Stat::make('Fallidos', $failed)
                ->description('Requieren reintento')
                ->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Videos codificados', $totalEncoded)
                ->description('Total con resolución lista'),
        ];

        return $stats;
    }

    private function driveStorageStat(bool $isConfigured): Stat
    {
        if (! $isConfigured) {
            return Stat::make('Google Drive', 'No configurado')
                ->color('gray');
        }

        try {
            $usage = app(GoogleDriveService::class)->getStorageUsage();

            $used = Number::fileSize($usage['used_bytes']);
            $total = Number::fileSize($usage['total_bytes']);
            $percent = $usage['used_percent'];

            $color = match (true) {
                $percent >= 90 => 'danger',
                $percent >= 70 => 'warning',
                default => 'success',
            };

            return Stat::make('Google Drive', "{$used} / {$total}")
                ->description("{$percent}% usado")
                ->color($color);
        } catch (\Throwable) {
            return Stat::make('Google Drive', 'Error')
                ->description('No se pudo obtener uso')
                ->color('gray');
        }
    }
}
