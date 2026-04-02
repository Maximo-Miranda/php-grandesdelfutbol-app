<?php

namespace App\Http\Controllers;

use App\Services\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class YouTubeAuthController extends Controller
{
    public function __construct(private GoogleAuthService $authService) {}

    public function redirect(Request $request): RedirectResponse
    {
        Gate::authorize('superAdmin');

        return redirect()->away($this->authService->getAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        Gate::authorize('superAdmin');

        $code = $request->string('code');

        if ($code->isEmpty()) {
            return redirect()->route('dashboard')->with('error', 'YouTube authorization was cancelled.');
        }

        try {
            $this->authService->handleCallback($code);
            Log::info('Google OAuth token saved successfully (YouTube + Drive)');
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('dashboard')->with('error', 'YouTube authorization failed: '.$e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'YouTube account connected successfully.');
    }
}
