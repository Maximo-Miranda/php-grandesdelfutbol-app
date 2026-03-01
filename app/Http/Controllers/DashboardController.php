<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\FootballMatch;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $clubIds = Club::query()
            ->forUser($user)
            ->pluck('id');

        return Inertia::render('Dashboard', [
            'upcomingMatches' => Inertia::defer(fn () => FootballMatch::query()
                ->whereIn('club_id', $clubIds)
                ->upcoming()
                ->with('club', 'field')
                ->withCount('attendances')
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get()
            ),
            'recentMatches' => Inertia::defer(fn () => FootballMatch::query()
                ->whereIn('club_id', $clubIds)
                ->completed()
                ->with('club')
                ->latest('ended_at')
                ->limit(5)
                ->get()
            ),
            'clubCount' => $clubIds->count(),
        ]);
    }
}
