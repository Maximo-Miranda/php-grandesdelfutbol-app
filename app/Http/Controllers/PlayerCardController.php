<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\Player;
use App\Models\PlayerProfile;
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

        $userPlayersQuery = Player::anyClub()
            ->whereIn('club_id', $clubIds)
            ->where('user_id', $user->id);

        $playerStats = (clone $userPlayersQuery)
            ->selectRaw('SUM(goals) as total_goals')
            ->selectRaw('SUM(assists) as total_assists')
            ->selectRaw('SUM(matches_played) as total_matches')
            ->selectRaw('SUM(saves) as total_saves')
            ->first();

        $playerIds = (clone $userPlayersQuery)->pluck('id');

        $profile = $user->playerProfile ?? new PlayerProfile;

        $matchesWithVideo = FootballMatch::query()
            ->whereIn('club_id', $clubIds)
            ->where('status', MatchStatus::Completed)
            ->whereHas('videoUpload', fn ($q) => $q->where('status', VideoUploadStatus::Ready))
            ->select('id', 'ulid', 'club_id', 'title', 'scheduled_at', 'duration_minutes')
            ->with(['club:id,ulid,name', 'videoUpload:id,football_match_id,duration_seconds,video_offset_seconds,status'])
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
                    $q->whereIn('player_id', $playerIds)
                        ->where('status', ReelStatus::Completed)
                        ->orWhere('requested_by', $user->id);
                })
                ->with('player', 'match', 'media')
                ->orderByDesc('created_at')
                ->simplePaginate(10, pageName: 'reels'),
        ]);
    }
}
