<?php

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Season;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeasonService
{
    public function activeFor(Club $club): Season
    {
        $existing = Season::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('status', SeasonStatus::Active)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Cache::lock("season-active-{$club->id}", 10)->block(5, function () use ($club) {
            return Season::query()->withoutGlobalScopes()
                ->where('club_id', $club->id)
                ->where('status', SeasonStatus::Active)
                ->first()
                ?? $this->createNextSeasonFor($club);
        });
    }

    public function createNextSeasonFor(Club $club, ?int $matchesCount = null): Season
    {
        $count = Season::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->count();

        return Season::query()->create([
            'club_id' => $club->id,
            'name' => 'Temporada #'.($count + 1),
            'matches_count' => $matchesCount ?? Season::DEFAULT_MATCHES_COUNT,
            'status' => SeasonStatus::Active,
        ]);
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

        $completed = $season->completedMatchesCount();

        if ($completed >= $season->matches_count) {
            $this->markCompleted($season);
        }
    }

    /**
     * Force-close a season (admin action) and open the next one.
     * Returns the new active season.
     */
    public function closeAndStartNext(Season $season): Season
    {
        return DB::transaction(function () use ($season) {
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
    }

    /**
     * Reopens a season that was closed but no longer meets the completion criteria
     * (e.g., a match was cancelled or marked friendly after closure).
     */
    public function reopenIfIncomplete(Season $season): void
    {
        $season->refresh();

        if ($season->isActive()) {
            return;
        }

        $hasAnotherActive = Season::query()->withoutGlobalScopes()
            ->where('club_id', $season->club_id)
            ->where('status', SeasonStatus::Active)
            ->exists();

        if ($hasAnotherActive) {
            return;
        }

        if ($season->completedMatchesCount() < $season->matches_count) {
            $season->update([
                'status' => SeasonStatus::Active,
                'completed_at' => null,
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
}
