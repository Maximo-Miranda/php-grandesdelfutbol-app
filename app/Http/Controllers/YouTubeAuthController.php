<?php

namespace App\Http\Controllers;

use App\Services\YouTubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        $code = $request->query('code');

        if (! $code) {
            return redirect()->route('dashboard')->with('error', 'YouTube authorization was cancelled.');
        }

        try {
            $this->youtubeService->handleCallback($code);
        } catch (\Throwable $e) {
            return redirect()->route('dashboard')->with('error', 'YouTube authorization failed: '.$e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'YouTube account connected successfully.');
    }
}
