<?php

namespace App\Filament\Widgets;

use App\Models\MatchVideoUpload;
use App\Services\GoogleAuthService;
use App\Services\YouTubeQuotaService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

        return [
            Stat::make('Google OAuth', $isConfigured ? 'Conectado' : 'No conectado')
                ->description($isConfigured ? 'Click para re-autorizar' : 'Click para conectar cuenta')
                ->descriptionIcon($isConfigured ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($isConfigured ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'onclick' => "window.location.href='{$authUrl}'",
                ]),
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
    }
}
