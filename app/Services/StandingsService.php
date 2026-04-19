<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
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
        $completedMatches = $this->completedMatchesQuery($season)
            ->whereNotNull('team_a_id')
            ->whereNotNull('team_b_id')
            ->orderByDesc('scheduled_at')
            ->get(['team_a_id', 'team_b_id', 'team_a_score', 'team_b_score', 'is_friendly']);

        $stats = $season->teams()->with('attachments')->get()
            ->mapWithKeys(fn ($team) => [$team->id => $this->initialRow($team)])
            ->all();

        foreach ($completedMatches as $match) {
            if ($match->is_friendly) {
                continue;
            }

            $this->apply($stats, $match->team_a_id, $match->team_a_score, $match->team_b_score);
            $this->apply($stats, $match->team_b_id, $match->team_b_score, $match->team_a_score);
        }

        $last5ByTeam = $this->last5Map($completedMatches, array_keys($stats));

        foreach ($stats as $teamId => &$row) {
            $row['DG'] = $row['GF'] - $row['GC'];
            $row['last5'] = $last5ByTeam[$teamId] ?? [];
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
        return FootballMatch::query()->withoutGlobalScopes()
            ->where('season_id', $season->id)
            ->with(['teamA.attachments', 'teamB.attachments'])
            ->orderByDesc('scheduled_at')
            ->get([
                'id', 'ulid', 'scheduled_at', 'status', 'is_friendly',
                'team_a_id', 'team_b_id',
                'team_a_name', 'team_b_name',
                'team_a_color', 'team_b_color',
                'team_a_score', 'team_b_score',
            ])
            ->map(fn (FootballMatch $m) => [
                'ulid' => $m->ulid,
                'scheduled_at' => $m->scheduled_at?->toIso8601String(),
                'status' => $m->status->value,
                'is_friendly' => $m->is_friendly,
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

    private function completedMatchesQuery(Season $season): Builder
    {
        return FootballMatch::query()->withoutGlobalScopes()
            ->where('season_id', $season->id)
            ->where('status', MatchStatus::Completed)
            ->whereNotNull('team_a_score')
            ->whereNotNull('team_b_score');
    }

    /**
     * @return array<string, mixed>
     */
    private function initialRow(Team $team): array
    {
        return [
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

    /**
     * @param  array<int, array<string, mixed>>  $stats
     */
    private function apply(array &$stats, int $teamId, int $gf, int $gc): void
    {
        if (! isset($stats[$teamId])) {
            return;
        }

        $row = &$stats[$teamId];
        $row['PJ']++;
        $row['GF'] += $gf;
        $row['GC'] += $gc;

        if ($gf > $gc) {
            $row['G']++;
            $row['Pts'] += 3;
        } elseif ($gf === $gc) {
            $row['E']++;
            $row['Pts'] += 1;
        } else {
            $row['P']++;
        }
    }

    /**
     * Build last-5 results per team from a single pre-loaded match collection (W|D|L|F, F = friendly).
     *
     * @param  Collection<int, FootballMatch>  $matches  ordered desc by scheduled_at
     * @param  array<int, int>  $teamIds
     * @return array<int, array<int, string>>
     */
    private function last5Map(Collection $matches, array $teamIds): array
    {
        $result = array_fill_keys($teamIds, []);

        foreach ($matches as $match) {
            foreach ([$match->team_a_id, $match->team_b_id] as $teamId) {
                if (! isset($result[$teamId]) || count($result[$teamId]) >= 5) {
                    continue;
                }
                $result[$teamId][] = $this->resultFor($match, $teamId);
            }
        }

        return array_map(fn (array $codes) => array_reverse($codes), $result);
    }

    private function resultFor(FootballMatch $match, int $teamId): string
    {
        if ($match->is_friendly) {
            return 'F';
        }

        $isA = $match->team_a_id === $teamId;
        $gf = $isA ? $match->team_a_score : $match->team_b_score;
        $gc = $isA ? $match->team_b_score : $match->team_a_score;

        return match ($gf <=> $gc) {
            1 => 'W',
            0 => 'D',
            -1 => 'L',
        };
    }
}
