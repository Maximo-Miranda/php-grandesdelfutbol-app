<?php

namespace App\Http\Controllers;

use App\Http\Requests\Match\StoreMatchEventRequest;
use App\Http\Requests\Match\UpdateMatchEventRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Services\MatchStatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchEventController extends Controller
{
    public function __construct(private MatchStatService $statService) {}

    public function store(StoreMatchEventRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $match->events()->create($request->validated());

        $this->refreshStatsIfFinalized($match);

        return back();
    }

    /** Lightweight PATCH — player_id / highlighted only (used by EventTimeline inline assign). */
    public function update(Request $request, Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'player_id' => ['nullable', 'exists:players,id'],
            'highlighted' => ['sometimes', 'boolean'],
        ]);

        $event->update($validated);

        $this->refreshStatsIfFinalized($match);

        return back();
    }

    /** Full PUT — all fields (used by edit Dialog). */
    public function fullUpdate(UpdateMatchEventRequest $request, Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        $event->update($request->validated());

        $this->refreshStatsIfFinalized($match);

        return back();
    }

    public function destroy(Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $event->delete();

        $this->refreshStatsIfFinalized($match);

        return back()->with('success', 'Evento eliminado.');
    }

    private function refreshStatsIfFinalized(FootballMatch $match): void
    {
        if ($match->stats_finalized_at) {
            $this->statService->finalizeStats($match);
        }
    }
}
