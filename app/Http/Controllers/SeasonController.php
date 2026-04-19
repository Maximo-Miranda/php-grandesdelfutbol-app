<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Http\Requests\Season\UpdateSeasonRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Services\SeasonService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
    ) {}

    public function index(Club $club): Response
    {
        Gate::authorize('viewAny', [Season::class, $club]);

        $seasons = $club->seasons()->orderByDesc('created_at')->get();

        // Single aggregate query avoids N+1 (played/completed/starts_on/ends_on per season)
        $aggregates = FootballMatch::query()->withoutGlobalScopes()
            ->whereIn('season_id', $seasons->pluck('id'))
            ->where('is_friendly', false)
            ->whereIn('status', [MatchStatus::Upcoming, MatchStatus::InProgress, MatchStatus::Completed])
            ->selectRaw('season_id,
                COUNT(*) AS played,
                COUNT(*) FILTER (WHERE status = ?) AS completed,
                MIN(scheduled_at) AS starts_on,
                MAX(scheduled_at) AS ends_on',
                [MatchStatus::Completed->value])
            ->groupBy('season_id')
            ->get()
            ->keyBy('season_id');

        $seasons = $seasons->map(function (Season $s) use ($aggregates) {
            $agg = $aggregates->get($s->id);

            return [
                'ulid' => $s->ulid,
                'name' => $s->name,
                'matches_count' => $s->matches_count,
                'status' => $s->status->value,
                'completed_at' => $s->completed_at?->toIso8601String(),
                'is_active' => $s->isActive(),
                'played' => (int) ($agg->played ?? 0),
                'completed' => (int) ($agg->completed ?? 0),
                'starts_on' => $agg?->starts_on ? CarbonImmutable::parse($agg->starts_on)->toIso8601String() : null,
                'ends_on' => $agg?->ends_on ? CarbonImmutable::parse($agg->ends_on)->toIso8601String() : null,
            ];
        });

        return Inertia::render('clubs/seasons/Index', [
            'club' => $club,
            'isAdmin' => $club->isAdminOrOwner(request()->user()),
            'seasons' => $seasons,
        ]);
    }

    public function update(UpdateSeasonRequest $request, Club $club, Season $season): RedirectResponse
    {
        $season->update(['matches_count' => (int) $request->input('matches_count')]);

        $this->seasons->finalizeIfComplete($season);

        return redirect()->route('clubs.seasons.index', $club)->with('success', 'Temporada actualizada.');
    }
}
