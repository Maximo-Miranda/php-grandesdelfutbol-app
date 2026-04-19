<?php

namespace App\Observers;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Services\SeasonService;
use App\Services\StatsRecalculationService;

class FootballMatchObserver
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly StatsRecalculationService $statsRecalc,
    ) {}

    public function creating(FootballMatch $match): void
    {
        $this->seasons->assignMatch($match);
        $this->syncDenormalizedTeamFields($match);
    }

    public function updating(FootballMatch $match): void
    {
        if ($match->isDirty(['team_a_id', 'team_b_id'])) {
            $this->syncDenormalizedTeamFields($match);
        }
    }

    public function saved(FootballMatch $match): void
    {
        $original = $match->getOriginal();

        $this->statsRecalc->handle($match, $original);
    }

    private function syncDenormalizedTeamFields(FootballMatch $match): void
    {
        $ids = array_filter([$match->team_a_id, $match->team_b_id]);
        $teams = $ids
            ? Team::query()->withoutGlobalScopes()->whereIn('id', $ids)->get()->keyBy('id')
            : collect();

        if ($match->team_a_id && $teamA = $teams->get($match->team_a_id)) {
            $match->team_a_name = $teamA->name;
            $match->team_a_color = $teamA->color;
        }

        if ($match->team_b_id && $teamB = $teams->get($match->team_b_id)) {
            $match->team_b_name = $teamB->name;
            $match->team_b_color = $teamB->color;
        } elseif ($match->exists && $match->team_b_id === null && $match->getOriginal('team_b_id') !== null) {
            $match->team_b_name = null;
            $match->team_b_color = null;
        }
    }
}
