<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Season;
use Illuminate\Support\Collection;

class StandingsService
{
    /**
     * Compute the team standings for a season, ordered by points.
     *
     * @return Collection<int, array{team_id: int, team_ulid: string, name: string, color: string, logo_url: ?string, PJ: int, G: int, E: int, P: int, GF: int, GC: int, DG: int, Pts: int, last5: array<int, string>}>
     */
    public function forSeason(Season $season): Collection
    {
        $matches = FootballMatch::query()->withoutGlobalScopes()
            ->where('season_id', $season->id)
            ->where('status', MatchStatus::Completed)
            ->where('is_friendly', false)
            ->whereNotNull('team_a_id')
            ->whereNotNull('team_b_id')
            ->whereNotNull('team_a_score')
            ->whereNotNull('team_b_score')
            ->get(['id', 'team_a_id', 'team_b_id', 'team_a_score', 'team_b_score']);

        $teams = $season->teams()->with('attachments')->get()->keyBy('id');

        $stats = [];
        foreach ($teams as $team) {
            $stats[$team->id] = [
                'team_id' => $team->id,
                'team_ulid' => $team->ulid,
                'name' => $team->name,
                'color' => $team->color,
                'logo_url' => $team->logo_url,
                'PJ' => 0,
                'G' => 0,
                'E' => 0,
                'P' => 0,
                'GF' => 0,
                'GC' => 0,
                'DG' => 0,
                'Pts' => 0,
                'last5' => [],
            ];
        }

        foreach ($matches as $match) {
            $this->apply($stats, $match->team_a_id, $match->team_b_id, $match->team_a_score, $match->team_b_score);
            $this->apply($stats, $match->team_b_id, $match->team_a_id, $match->team_b_score, $match->team_a_score);
        }

        foreach ($stats as &$row) {
            $row['DG'] = $row['GF'] - $row['GC'];
            $row['last5'] = $this->last5For($season, $row['team_id']);
        }
        unset($row);

        return collect(array_values($stats))
            ->sortBy([
                ['Pts', 'desc'],
                ['DG', 'desc'],
                ['GF', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     */
    private function apply(array &$stats, int $teamId, int $opponentId, int $gf, int $gc): void
    {
        if (! isset($stats[$teamId])) {
            return;
        }

        $stats[$teamId]['PJ']++;
        $stats[$teamId]['GF'] += $gf;
        $stats[$teamId]['GC'] += $gc;

        if ($gf > $gc) {
            $stats[$teamId]['G']++;
            $stats[$teamId]['Pts'] += 3;
        } elseif ($gf === $gc) {
            $stats[$teamId]['E']++;
            $stats[$teamId]['Pts'] += 1;
        } else {
            $stats[$teamId]['P']++;
        }
    }

    /**
     * Match list for a season — used by the calendar view.
     *
     * @return Collection<int, array{
     *     ulid: string,
     *     scheduled_at: string,
     *     status: string,
     *     is_friendly: bool,
     *     team_a: array{name: string, color: ?string, logo_url: ?string, score: ?int},
     *     team_b: ?array{name: string, color: ?string, logo_url: ?string, score: ?int}
     * }>
     */
    public function matchesForSeason(Season $season): Collection
    {
        $matches = FootballMatch::query()->withoutGlobalScopes()
            ->where('season_id', $season->id)
            ->with(['teamA.attachments', 'teamB.attachments'])
            ->orderByDesc('scheduled_at')
            ->get([
                'id', 'ulid', 'scheduled_at', 'status', 'is_friendly',
                'team_a_id', 'team_b_id',
                'team_a_name', 'team_b_name',
                'team_a_color', 'team_b_color',
                'team_a_score', 'team_b_score',
            ]);

        return $matches->map(fn (FootballMatch $m) => [
            'ulid' => $m->ulid,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
            'status' => $m->status->value,
            'is_friendly' => (bool) $m->is_friendly,
            'team_a' => [
                'name' => $m->teamA?->name ?? $m->team_a_name,
                'color' => $m->teamA?->color ?? $m->team_a_color,
                'logo_url' => $m->teamA?->logo_url,
                'score' => $m->team_a_score,
            ],
            'team_b' => $m->team_b_id || $m->team_b_name ? [
                'name' => $m->teamB?->name ?? $m->team_b_name,
                'color' => $m->teamB?->color ?? $m->team_b_color,
                'logo_url' => $m->teamB?->logo_url,
                'score' => $m->team_b_score,
            ] : null,
        ]);
    }

    /**
     * @return array<int, string> W|D|L|F (F = friendly)
     */
    private function last5For(Season $season, int $teamId): array
    {
        $matches = FootballMatch::query()->withoutGlobalScopes()
            ->where('season_id', $season->id)
            ->where('status', MatchStatus::Completed)
            ->where(function ($q) use ($teamId) {
                $q->where('team_a_id', $teamId)->orWhere('team_b_id', $teamId);
            })
            ->whereNotNull('team_a_score')
            ->whereNotNull('team_b_score')
            ->orderByDesc('scheduled_at')
            ->limit(5)
            ->get(['team_a_id', 'team_b_id', 'team_a_score', 'team_b_score', 'is_friendly']);

        return $matches->map(function (FootballMatch $match) use ($teamId) {
            if ($match->is_friendly) {
                return 'F';
            }

            $isA = $match->team_a_id === $teamId;
            $gf = $isA ? $match->team_a_score : $match->team_b_score;
            $gc = $isA ? $match->team_b_score : $match->team_a_score;

            return match (true) {
                $gf > $gc => 'W',
                $gf === $gc => 'D',
                default => 'L',
            };
        })->reverse()->values()->all();
    }
}
