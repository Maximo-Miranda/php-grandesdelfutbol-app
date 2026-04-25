<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Enums\AttendanceStatus;
use App\Models\Club;
use App\Models\Field;
use App\Models\FootballMatch;
use App\Models\Venue;
use Inertia\Inertia;
use Inertia\Response;

class PublicClubController extends Controller
{
    public function show(Club $club): Response
    {
        abort_unless($club->is_public, 404);

        $club->load(['attachments' => fn ($q) => $q->where('collection', AttachmentCollection::Logo)]);

        $club->loadCount([
            'matches as completed_matches_count' => fn ($q) => $q->completed(),
            'matches as upcoming_matches_count' => fn ($q) => $q->upcoming(),
            'players' => fn ($q) => $q->where('is_active', true),
        ]);

        $matchEager = [
            'field.venue:id,name,address',
            'season:id,name',
            'teamA.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::TeamLogo),
            'teamB.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::TeamLogo),
        ];

        $nextMatches = $club->matches()
            ->upcoming()
            ->with($matchEager)
            ->withCount(['attendances' => fn ($q) => $q->where('status', AttendanceStatus::Confirmed)])
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get()
            ->map(fn ($match) => $this->presentMatch($match));

        $recentMatches = $club->matches()
            ->completed()
            ->with($matchEager)
            ->withCount(['attendances' => fn ($q) => $q->where('status', AttendanceStatus::Confirmed)])
            ->orderByDesc('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn ($match) => $this->presentMatch($match));

        $teams = $club->teams()
            ->with(['attachments' => fn ($q) => $q->where('collection', AttachmentCollection::TeamLogo)])
            ->orderBy('name')
            ->get(['id', 'ulid', 'club_id', 'name', 'color'])
            ->map(fn ($team) => [
                'id' => $team->id,
                'ulid' => $team->ulid,
                'name' => $team->name,
                'color' => $team->color,
                'logo_url' => $team->logo_url,
            ]);

        return Inertia::render('clubs/Public', [
            'club' => [
                'ulid' => $club->ulid,
                'slug' => $club->slug,
                'name' => $club->name,
                'description' => $club->description,
                'logo_url' => $club->logo_url,
                'completed_matches_count' => $club->completed_matches_count,
                'upcoming_matches_count' => $club->upcoming_matches_count,
                'players_count' => $club->players_count,
            ],
            'nextMatches' => $nextMatches,
            'recentMatches' => $recentMatches,
            'teams' => $teams,
            'appUrl' => config('app.url'),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentMatch(FootballMatch $match): array
    {
        return [
            'id' => $match->id,
            'ulid' => $match->ulid,
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at,
            'status' => $match->status,
            'is_friendly' => (bool) $match->is_friendly,
            'max_players' => $match->max_players,
            'attendances_count' => $match->attendances_count ?? 0,
            'team_a_name' => $match->team_a_name,
            'team_b_name' => $match->team_b_name,
            'team_a_score' => $match->team_a_score,
            'team_b_score' => $match->team_b_score,
            'team_a_color' => $match->team_a_color,
            'team_b_color' => $match->team_b_color,
            'team_a_logo_url' => $match->teamA?->logo_url,
            'team_b_logo_url' => $match->teamB?->logo_url,
            'share_token' => $match->share_token,
            'season' => $match->season ? ['name' => $match->season->name] : null,
            'field' => $this->presentField($match->field),
        ];
    }

    /** @return array<string, mixed>|null */
    private function presentField(?Field $field): ?array
    {
        if ($field === null) {
            return null;
        }

        return [
            'id' => $field->id,
            'name' => $field->name,
            'venue' => $this->presentVenue($field->venue),
        ];
    }

    /** @return array<string, mixed>|null */
    private function presentVenue(?Venue $venue): ?array
    {
        if ($venue === null) {
            return null;
        }

        return [
            'id' => $venue->id,
            'name' => $venue->name,
            'address' => $venue->address,
        ];
    }
}
