<?php

namespace App\Http\Controllers;

use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\SeasonService;
use App\Services\StandingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use NotificationChannels\WebPush\PushSubscription;

class StandingsController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly StandingsService $standings,
    ) {}

    public function index(Request $request, Club $club): Response
    {
        Gate::authorize('viewAny', [Team::class, $club]);

        $seasons = $club->seasons()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Season $s) => [
                'ulid' => $s->ulid,
                'name' => $s->name,
                'matches_count' => $s->matches_count,
                'status' => $s->status->value,
                'completed_at' => $s->completed_at?->toIso8601String(),
                'is_active' => $s->isActive(),
            ]);

        $selected = null;
        $seasonUlid = $request->string('season')->toString();
        if ($seasonUlid !== '') {
            $selected = $club->seasons()->where('ulid', $seasonUlid)->first();
        }
        $selected ??= $club->seasons()->active()->first() ?? $club->seasons()->latest()->first();

        if ($selected === null) {
            $selected = $this->seasons->activeFor($club);
            $seasons = $seasons->push([
                'ulid' => $selected->ulid,
                'name' => $selected->name,
                'matches_count' => $selected->matches_count,
                'status' => $selected->status->value,
                'completed_at' => null,
                'is_active' => true,
            ]);
        }

        $progress = $this->seasons->progress($selected);
        $teamStandings = $this->standings->forSeason($selected);

        $user = $request->user();
        $isAdmin = $club->isAdminOrOwner($user);

        $playerBase = fn () => $club->players()
            ->with('user.playerProfile')
            ->when($isAdmin, fn ($q) => $q->select('players.*')->addSelect([
                'has_push' => PushSubscription::query()
                    ->selectRaw('1')
                    ->whereColumn('subscribable_id', 'players.user_id')
                    ->where('subscribable_type', User::class)
                    ->limit(1),
            ]));

        return Inertia::render('clubs/standings/Index', [
            'club' => $club,
            'isAdmin' => $isAdmin,
            'tab' => $request->string('tab')->toString() ?: 'teams',
            'seasons' => $seasons->values(),
            'selectedSeason' => [
                'ulid' => $selected->ulid,
                'name' => $selected->name,
                'matches_count' => $selected->matches_count,
                'status' => $selected->status->value,
                'is_active' => $selected->isActive(),
                'starts_on' => $selected->startsOn()?->toIso8601String(),
                'ends_on' => $selected->projectedEndsOn()?->toIso8601String(),
            ],
            'progress' => $progress,
            'teamStandings' => $teamStandings,
            'seasonMatches' => Inertia::defer(fn () => $this->standings->matchesForSeason($selected)),
            'players' => Inertia::scroll(
                fn () => $playerBase()
                    ->where(fn ($q) => $q->whereNull('position')->orWhere('position', '!=', PlayerPosition::Gk))
                    ->orderByRaw('goals DESC, matches_played ASC, assists DESC')
                    ->simplePaginate(20),
            ),
            'goalkeepers' => fn () => $playerBase()
                ->where('position', PlayerPosition::Gk)
                ->orderByRaw('saves DESC, matches_played ASC')
                ->get(),
        ]);
    }
}
