<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NtfyService
{
    public function publish(User $user, array $payload): void
    {
        $url = rtrim(config('services.ntfy.url'), '/');
        $topic = $user->ntfyTopic();

        try {
            $request = Http::acceptJson();

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
        $this->publish($user, [
            'title' => 'Grandes del Futbol',
            'message' => 'Las notificaciones push están funcionando correctamente.',
            'priority' => 3,
            'tags' => ['white_check_mark', 'soccer'],
            'click' => url('/settings/notifications'),
        ]);
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
