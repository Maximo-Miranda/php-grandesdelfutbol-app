<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\NtfyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NtfyController extends Controller
{
    public function __construct(private NtfyService $ntfyService) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $ntfyUrl = $this->ntfyService->baseUrl();

        return Inertia::render('settings/Notifications', [
            'ntfyTopic' => $user->ntfyTopic(),
            'ntfyEnabled' => $user->hasNtfyEnabled(),
            'ntfyUrl' => $ntfyUrl,
            'ntfyHost' => parse_url($ntfyUrl, PHP_URL_HOST),
        ]);
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $this->ntfyService->sendTestNotification($request->user());

        return back()->with('success', 'Notificación de prueba enviada.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->ntfyService->confirmSetup($request->user());

        return back()->with('success', 'Notificaciones push habilitadas.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->ntfyService->disable($request->user());

        return back()->with('success', 'Notificaciones push deshabilitadas.');
    }
}
