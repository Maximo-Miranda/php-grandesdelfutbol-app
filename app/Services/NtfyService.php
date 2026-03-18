<?php

namespace App\Services;

use App\Models\Club;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NtfyService
{
    public function baseUrl(): string
    {
        return rtrim(config('services.ntfy.url'), '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \Throwable
     */
    public function publish(Club $club, array $payload): void
    {
        $url = $this->baseUrl();
        $payload['topic'] = $club->ntfyTopic();

        try {
            Http::asJson()
                ->when(config('services.ntfy.token'), fn ($http, $token) => $http->withToken($token))
                ->post($url, $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('ntfy: failed to send notification', [
                'club_id' => $club->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function sendTestNotification(Club $club): void
    {
        $this->publish($club, NtfyMessage::create('Las notificaciones push están funcionando correctamente.')
            ->title('Grandes del Futbol')
            ->tags('white_check_mark,soccer')
            ->toArray());
    }
}
