<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Models\Player;
use App\Models\PlayerProfile;
use Inertia\Inertia;
use Inertia\Response;

class PublicPlayerController extends Controller
{
    public function show(Player $player): Response
    {
        $player->load(['club', 'user.playerProfile']);

        abort_unless($player->club->is_public, 404);
        abort_unless($player->isPubliclyVisible(), 404);

        $player->load([
            'club.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::Logo),
            'teams' => fn ($q) => $q->orderByDesc('id'),
            'teams.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::TeamLogo),
        ]);

        return Inertia::render('players/Public', [
            'player' => [
                'ulid' => $player->ulid,
                'name' => $player->display_name,
                'photo_url' => $player->photo_url,
                'jersey_number' => $player->jersey_number,
                'position' => $player->position?->value,
                'position_label' => $player->position?->label(),
                'is_active' => $player->is_active,
                'stats' => [
                    'goals' => (int) $player->goals,
                    'assists' => (int) $player->assists,
                    'matches_played' => (int) $player->matches_played,
                    'yellow_cards' => (int) $player->yellow_cards,
                    'red_cards' => (int) $player->red_cards,
                    'saves' => (int) $player->saves,
                ],
            ],
            'profile' => $this->presentProfile($player->user?->playerProfile),
            'club' => [
                'ulid' => $player->club->ulid,
                'slug' => $player->club->slug,
                'name' => $player->club->name,
                'logo_url' => $player->club->logo_url,
            ],
            'teams' => $player->teams->map(fn ($team) => [
                'ulid' => $team->ulid,
                'name' => $team->name,
                'color' => $team->color,
                'logo_url' => $team->logo_url,
            ])->values(),
            'appUrl' => config('app.url'),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function presentProfile(?PlayerProfile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        return [
            'nickname' => $profile->nickname,
            'nationality' => $profile->nationality,
            'preferred_position' => $profile->preferred_position,
            'bio' => $profile->bio,
        ];
    }
}
