<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\ReelStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchReel;
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
            ->selectRaw('SUM(assists) as total_assists')
            ->selectRaw('SUM(matches_played) as total_matches')
            ->selectRaw('SUM(saves) as total_saves')
            ->first();

        $profile = $user->playerProfile ?? new PlayerProfile;

        $playerIds = Player::withoutGlobalScope(ClubScope::class)
            ->whereIn('club_id', $clubIds)
            ->where('user_id', $user->id)
            ->pluck('id');

        $matchesWithVideo = FootballMatch::query()
            ->whereIn('club_id', $clubIds)
            ->where('status', MatchStatus::Completed)
            ->whereNotNull('youtube_url')
            ->select('id', 'ulid', 'club_id', 'title', 'scheduled_at', 'video_duration_seconds', 'duration_minutes')
            ->with('club:id,ulid,name')
            ->orderByDesc('scheduled_at')
            ->get();

        return Inertia::render('PlayerCard', [
            'playerStats' => [
                'goals' => (int) ($playerStats->total_goals ?? 0),
                'assists' => (int) ($playerStats->total_assists ?? 0),
                'matches' => (int) ($playerStats->total_matches ?? 0),
                'saves' => (int) ($playerStats->total_saves ?? 0),
            ],
            'profile' => $profile,
            'clubs' => $clubs,
            'matchesWithVideo' => $matchesWithVideo,
            'reels' => fn () => MatchReel::query()
                ->where(function ($q) use ($playerIds, $user) {
                    $q->where(function ($q) use ($playerIds) {
                        $q->whereIn('player_id', $playerIds)
                            ->where('status', ReelStatus::Completed);
                    })->orWhere('requested_by', $user->id);
                })
                ->with('player', 'match', 'media')
                ->orderByDesc('created_at')
                ->simplePaginate(10, pageName: 'reels'),
        ]);
    }
}
