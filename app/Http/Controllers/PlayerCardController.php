<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerProfile;
use App\Models\Scopes\ClubScope;
use Inertia\Inertia;
use Inertia\Response;

class PlayerCardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $clubs = Club::query()
            ->forUser($user)
            ->get();

        $clubIds = $clubs->pluck('id');

        $playerStats = Player::withoutGlobalScope(ClubScope::class)
            ->whereIn('club_id', $clubIds)
            ->where('user_id', $user->id)
            ->selectRaw('SUM(goals) as total_goals')
            ->selectRaw('SUM(matches_played) as total_matches')
            ->selectRaw('SUM(yellow_cards) as total_yellow_cards')
            ->selectRaw('SUM(red_cards) as total_red_cards')
            ->first();

        $profile = $user->playerProfile ?? new PlayerProfile;

        return Inertia::render('PlayerCard', [
            'playerStats' => [
                'goals' => (int) ($playerStats->total_goals ?? 0),
                'matches' => (int) ($playerStats->total_matches ?? 0),
                'yellowCards' => (int) ($playerStats->total_yellow_cards ?? 0),
                'redCards' => (int) ($playerStats->total_red_cards ?? 0),
            ],
            'profile' => $profile,
            'clubs' => $clubs,
        ]);
    }
}
