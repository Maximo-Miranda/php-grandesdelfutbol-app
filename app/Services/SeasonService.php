<?php

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeasonService
{
    public function activeFor(Club $club): Season
    {
        $existing = $this->activeSeasonQuery($club->id)->first();

        if ($existing) {
            return $existing;
        }

        return Cache::lock("season-active-{$club->id}", 10)->block(5, function () use ($club): Season {
            return $this->activeSeasonQuery($club->id)->first()
                ?? $this->createNextSeasonFor($club);
        });
    }

    public function createNextSeasonFor(Club $club, ?int $matchesCount = null): Season
    {
        $count = $this->seasonsQuery($club->id)->count();

        $season = Season::query()->create([
            'club_id' => $club->id,
            'name' => 'Temporada #'.($count + 1),
            'matches_count' => $matchesCount ?? Season::DEFAULT_MATCHES_COUNT,
            'status' => SeasonStatus::Active,
        ]);

        Log::info('season.created', [
            'club_id' => $club->id,
            'season_id' => $season->id,
            'name' => $season->name,
            'matches_count' => $season->matches_count,
        ]);

        return $season;
    }

    public function assignMatch(FootballMatch $match): void
    {
        if ($match->season_id) {
            return;
        }

        $club = $match->club ?? Club::find($match->club_id);

        if (! $club) {
            return;
        }

        $match->season_id = $this->activeFor($club)->id;
    }

    public function finalizeIfComplete(Season $season): void
    {
        $season->refresh();

        if (! $season->isActive()) {
            return;
        }

        if ($season->completedMatchesCount() >= $season->matches_count) {
            $this->markCompleted($season);
        }
    }

    public function closeAndStartNext(Season $season): Season
    {
        return DB::transaction(function () use ($season): Season {
            if ($season->isActive()) {
                $this->markCompleted($season);
            }

            return $this->createNextSeasonFor($season->club);
        });
    }

    private function markCompleted(Season $season): void
    {
        $season->update([
            'status' => SeasonStatus::Completed,
            'completed_at' => now(),
        ]);

        Log::info('season.completed', [
            'club_id' => $season->club_id,
            'season_id' => $season->id,
            'name' => $season->name,
            'completed_count' => $season->completedMatchesCount(),
        ]);
    }

    public function reopenIfIncomplete(Season $season): void
    {
        $season->refresh();

        if ($season->isActive()) {
            return;
        }

        if ($this->activeSeasonQuery($season->club_id)->exists()) {
            return;
        }

        if ($season->completedMatchesCount() < $season->matches_count) {
            $season->update([
                'status' => SeasonStatus::Active,
                'completed_at' => null,
            ]);

            Log::info('season.reopened', [
                'club_id' => $season->club_id,
                'season_id' => $season->id,
                'name' => $season->name,
                'completed_count' => $season->completedMatchesCount(),
                'matches_count' => $season->matches_count,
            ]);
        }
    }

    /**
     * @return array{played: int, total: int, completed: int}
     */
    public function progress(Season $season): array
    {
        return [
            'played' => $season->playedMatchesCount(),
            'completed' => $season->completedMatchesCount(),
            'total' => $season->matches_count,
        ];
    }

    private function seasonsQuery(int $clubId): Builder
    {
        return Season::query()->withoutGlobalScopes()->where('club_id', $clubId);
    }

    private function activeSeasonQuery(int $clubId): Builder
    {
        return $this->seasonsQuery($clubId)->where('status', SeasonStatus::Active);
    }
}
