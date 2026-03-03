<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ClubSwitchController extends Controller
{
    public function __invoke(Club $club, ClubService $clubService): RedirectResponse
    {
        Gate::authorize('view', $club);

        $clubService->switchToClub(auth()->user(), $club);

        return redirect()->route('clubs.show', $club);
    }
}
