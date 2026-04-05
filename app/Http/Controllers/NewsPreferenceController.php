<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsPreferenceController extends Controller
{
    public function create(): Response
    {
        $user = auth()->user();
        $preference = $user->newsPreference;

        return Inertia::render('news/Preferences', [
            'preference' => $preference,
        ]);
    }

    public function store(): RedirectResponse
    {
        // Stub for Phase 2
        return redirect()->route('news.feed');
    }

    public function update(): RedirectResponse
    {
        // Stub for Phase 2
        return redirect()->route('news.feed');
    }
}
