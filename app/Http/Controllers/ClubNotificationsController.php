<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ClubNotificationsController extends Controller
{
    public function show(Club $club): Response
    {
        Gate::authorize('view', $club);

        return Inertia::render('clubs/Notifications', [
            'club' => $club,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function sendTest(Club $club, Request $request): RedirectResponse
    {
        Gate::authorize('view', $club);

        $user = $request->user();

        if (! $user->pushSubscriptions()->exists()) {
            return back()->with('error', 'Primero activa las notificaciones push.');
        }

        $user->notify(new class($club->name) extends Notification
        {
            public function __construct(private string $clubName) {}

            /** @return array<int, string> */
            public function via(object $notifiable): array
            {
                return [WebPushChannel::class];
            }

            public function toWebPush(object $notifiable, object $notification): WebPushMessage
            {
                return (new WebPushMessage)
                    ->title('Notificaciones activas')
                    ->body("Las notificaciones push de {$this->clubName} están funcionando correctamente.")
                    ->icon('/pwa-192x192.png')
                    ->badge('/badge-96x96.png')
                    ->tag('test-notification');
            }
        });

        return back()->with('success', 'Notificación de prueba enviada.');
    }
}
