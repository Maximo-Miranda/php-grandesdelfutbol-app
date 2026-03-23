<?php

namespace App\Http\Controllers;

use App\Services\YouTubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class YouTubeAuthController extends Controller
{
    public function __construct(private YouTubeService $youtubeService) {}

    public function redirect(Request $request): RedirectResponse
    {
        Gate::authorize('superAdmin');

        return redirect()->away($this->youtubeService->getAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        Gate::authorize('superAdmin');

        $code = $request->string('code');

        if ($code->isEmpty()) {
            return redirect()->route('dashboard')->with('error', 'YouTube authorization was cancelled.');
        }

        try {
            $this->youtubeService->handleCallback($code);
            Log::info('YouTube token saved successfully');
        } catch (\Throwable $e) {
            Log::error('YouTube callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('dashboard')->with('error', 'YouTube authorization failed: '.$e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'YouTube account connected successfully.');
    }
}
