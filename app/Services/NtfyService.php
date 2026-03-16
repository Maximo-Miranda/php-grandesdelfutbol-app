<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NtfyService
{
    public function baseUrl(): string
    {
        return rtrim(config('services.ntfy.url'), '/');
    }

    public function publish(User $user, array $payload): void
    {
        $url = $this->baseUrl();
        $topic = $user->ntfyTopic();

        try {
            $request = Http::asJson();

            $token = config('services.ntfy.token');
            if ($token) {
                $request = $request->withToken($token);
            }

            $request->post("{$url}/{$topic}", $payload);
        } catch (\Throwable $e) {
            Log::warning('ntfy: failed to send notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
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
