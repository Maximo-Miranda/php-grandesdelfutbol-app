<?php

namespace App\Http\Controllers;

use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __invoke(ClubService $clubService): Response|RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return Inertia::render('Welcome', [
                'canRegister' => Features::enabled(Features::registration()),
            ]);
        }

        $club = $clubService->resolveForUser($user, $user->last_club_id);

        if ($club) {
            return redirect()->route('clubs.show', $club);
        }

        return redirect()->route('dashboard');
    }
}
