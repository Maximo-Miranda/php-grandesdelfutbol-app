<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Services\ClubContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        app(ClubContext::class)->clear();

        $user = auth()->user();

        $clubs = Club::query()
            ->forUser($user)
            ->withCount([
                'members',
                'matches',
                'matches as upcoming_matches_count' => function ($query) {
                    $query->upcoming();
                },
            ])
            ->get();

        if ($clubs->isEmpty()) {
            $invitation = ClubInvitation::query()
                ->where('email', $user->email)
                ->valid()
                ->first();

            if ($invitation) {
                return redirect()->route('invitations.show', $invitation->token);
            }

            return redirect()->route('clubs.create');
        }

        $clubIds = $clubs->pluck('id');

        $topClubs = $clubs
            ->sortByDesc(fn (Club $club) => $club->matches_count + $club->upcoming_matches_count)
            ->take(3)
            ->values();

        $playerStats = Player::query()
            ->whereIn('club_id', $clubIds)
            ->where('user_id', $user->id)
            ->selectRaw('SUM(goals) as total_goals')
            ->selectRaw('SUM(assists) as total_assists')
            ->selectRaw('SUM(matches_played) as total_matches')
            ->selectRaw('SUM(yellow_cards) as total_yellow_cards')
            ->selectRaw('SUM(red_cards) as total_red_cards')
            ->first();

        return Inertia::render('Dashboard', [
            'topClubs' => $topClubs,
            'playerStats' => [
                'goals' => (int) ($playerStats->total_goals ?? 0),
                'assists' => (int) ($playerStats->total_assists ?? 0),
                'matches' => (int) ($playerStats->total_matches ?? 0),
                'yellowCards' => (int) ($playerStats->total_yellow_cards ?? 0),
                'redCards' => (int) ($playerStats->total_red_cards ?? 0),
            ],
            'upcomingMatches' => Inertia::scroll(fn () => FootballMatch::query()
                ->whereIn('club_id', $clubIds)
                ->upcoming()
                ->with('club', 'field')
                ->withCount('attendances')
                ->orderBy('scheduled_at')
                ->simplePaginate(10)
            ),
            'recentMatches' => Inertia::defer(fn () => FootballMatch::query()
                ->whereIn('club_id', $clubIds)
                ->completed()
                ->with('club')
                ->latest('ended_at')
                ->limit(5)
                ->get()
            ),
            'pendingInvitations' => ClubInvitation::query()
                ->where('email', $user->email)
                ->valid()
                ->with('club')
                ->get(),
        ]);
    }
}
