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

        return Inertia::render('clubs/seasons/Index', [
            'club' => $club,
            'isAdmin' => $club->isAdminOrOwner(request()->user()),
            'seasons' => $seasons->map(fn (Season $season): array => $this->presentSeason($season, $aggregates->get($season->id))),
        ]);
    }

    public function update(UpdateSeasonRequest $request, Club $club, Season $season): RedirectResponse
    {
        $data = $request->validated();

        $season->update($data);

        if (isset($data['matches_count'])) {
            $this->seasons->finalizeIfComplete($season);
        }

        return redirect()->route('clubs.seasons.index', $club)->with('success', 'Temporada actualizada.');
    }

    public function close(Club $club, Season $season): RedirectResponse
    {
        Gate::authorize('close', $season);

        $newSeason = $this->seasons->closeAndStartNext($season);

        return redirect()->route('clubs.seasons.index', $club)
            ->with('success', "{$season->name} cerrada. Se creó {$newSeason->name} como temporada activa.");
    }

    /**
     * @return array{ulid: string, name: string, matches_count: int, status: string, completed_at: ?string, is_active: bool, played: int, completed: int, starts_on: ?string, ends_on: ?string}
     */
    private function presentSeason(Season $season, mixed $aggregate): array
    {
        return [
            'ulid' => $season->ulid,
            'name' => $season->name,
            'matches_count' => $season->matches_count,
            'status' => $season->status->value,
            'completed_at' => $season->completed_at?->toIso8601String(),
            'is_active' => $season->isActive(),
            'played' => (int) ($aggregate->played ?? 0),
            'completed' => (int) ($aggregate->completed ?? 0),
            'starts_on' => isset($aggregate->starts_on) ? CarbonImmutable::parse($aggregate->starts_on)->toIso8601String() : null,
            'ends_on' => isset($aggregate->ends_on) ? CarbonImmutable::parse($aggregate->ends_on)->toIso8601String() : null,
        ];
    }
}
