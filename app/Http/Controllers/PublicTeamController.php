<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\Team;
use App\Services\StandingsService;
use Inertia\Inertia;
use Inertia\Response;

class PublicTeamController extends Controller
{
    public function __construct(private StandingsService $standings) {}

    public function show(Team $team): Response
    {
        $team->load([
            'season',
            'club.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::Logo),
            'attachments' => fn ($q) => $q->whereIn('collection', [AttachmentCollection::TeamLogo, AttachmentCollection::TeamCover]),
            'coach.user.playerProfile',
            'captain.user.playerProfile',
            'players.user.playerProfile',
        ]);

        abort_unless($team->club->is_public, 404);

        $standings = $this->standings->forSeason($team->season);
        $row = $standings->firstWhere('team_id', $team->id) ?? [
            'PJ' => 0, 'G' => 0, 'E' => 0, 'P' => 0, 'GF' => 0, 'GC' => 0, 'DG' => 0, 'Pts' => 0, 'last5' => [],
        ];

        $recentMatches = FootballMatch::query()
            ->where('season_id', $team->season_id)
            ->where(fn ($q) => $q->where('team_a_id', $team->id)->orWhere('team_b_id', $team->id))
            ->orderByDesc('scheduled_at')
            ->limit(10)
            ->get([
                'id', 'ulid', 'title', 'scheduled_at',
                'team_a_id', 'team_b_id', 'team_a_name', 'team_b_name',
                'team_a_score', 'team_b_score', 'status', 'is_friendly',
                'share_token',
            ]);

        return Inertia::render('teams/Public', [
            'team' => [
                'id' => $team->id,
                'ulid' => $team->ulid,
                'name' => $team->name,
                'color' => $team->color,
                'bio' => $team->bio,
                'logo_url' => $team->logo_url,
                'cover_url' => $team->cover_url,
                'season' => [
                    'ulid' => $team->season->ulid,
                    'name' => $team->season->name,
                    'is_active' => $team->season->isActive(),
                ],
                'coach' => $team->coach ? $this->presentPlayer($team->coach) : null,
                'captain' => $team->captain ? $this->presentPlayer($team->captain) : null,
                'players' => $team->players->map(fn (Player $p) => $this->presentPlayer($p))->values(),
                'players_count' => $team->players->count(),
            ],
            'club' => [
                'ulid' => $team->club->ulid,
                'slug' => $team->club->slug,
                'name' => $team->club->name,
                'logo_url' => $team->club->logo_url,
            ],
            'stats' => $row,
            'recentMatches' => $recentMatches,
            'appUrl' => config('app.url'),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentPlayer(Player $player): array
    {
        return [
            'id' => $player->id,
            'ulid' => $player->ulid,
            'name' => $player->display_name,
            'jersey_number' => $player->jersey_number,
            'position' => $player->position?->value,
            'photo_url' => $player->photo_url,
        ];
    }
}
