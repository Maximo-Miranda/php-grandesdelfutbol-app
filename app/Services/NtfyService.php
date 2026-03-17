<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NtfyService
{
    public function baseUrl(): string
    {
        return rtrim(config('services.ntfy.url'), '/');
    }

    /**
     * @throws RequestException
     * @throws \Throwable
     * @throws ConnectionException
     */
    public function publish(User $user, array $payload): void
    {
        $url = $this->baseUrl();
        $payload['topic'] = $user->ntfyTopic();

        try {
            Http::asJson()
                ->when(config('services.ntfy.token'), fn ($http, $token) => $http->withToken($token))
                ->post($url, $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('ntfy: failed to send notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function sendTestNotification(User $user): void
    {
        $this->publish($user, NtfyMessage::create('Las notificaciones push están funcionando correctamente.')
            ->title('Grandes del Futbol')
            ->tags('white_check_mark,soccer')
            ->click(route('ntfy.edit'))
            ->toArray());
    }

    public function confirmSetup(User $user): void
    {
        $user->update(['ntfy_enabled_at' => now()]);
    }

    public function disable(User $user): void
    {
        $user->update(['ntfy_enabled_at' => null]);
    }
}
