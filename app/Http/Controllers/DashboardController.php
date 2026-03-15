<?php

namespace App\Http\Controllers;

use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(ClubService $clubService): RedirectResponse
    {
        $user = auth()->user();
        $club = $clubService->resolveForUser($user, $user->last_club_id);

        if ($club) {
            return redirect()->route('clubs.show', $club);
        }

        return redirect()->route('clubs.index');
    }
}
