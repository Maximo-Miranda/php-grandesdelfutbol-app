<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\NtfyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClubNotificationsController extends Controller
{
    public function show(Club $club, NtfyService $ntfyService): Response
    {
        Gate::authorize('view', $club);

        return Inertia::render('clubs/Notifications', [
            'club' => $club,
            'ntfyTopic' => $club->ntfyTopic(),
            'ntfyUrl' => $ntfyService->baseUrl(),
            'ntfyHost' => parse_url($ntfyService->baseUrl(), PHP_URL_HOST),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function sendTest(Club $club, NtfyService $ntfyService): RedirectResponse
    {
        Gate::authorize('update', $club);

        $ntfyService->sendTestNotification($club);

        return back()->with('success', 'Notificación de prueba enviada al canal del club.');
    }
}
