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

        return $this->recalculateAndRedirect($match);
    }

    /** Lightweight PATCH -- player_id / highlighted only (used by EventTimeline inline assign). */
    public function update(Request $request, Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'player_id' => ['nullable', 'exists:players,id'],
            'highlighted' => ['sometimes', 'boolean'],
        ]);

        $event->update($validated);

        return $this->recalculateAndRedirect($match);
    }

    /** Full PUT -- all fields (used by edit Dialog). */
    public function fullUpdate(UpdateMatchEventRequest $request, Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        $event->update($request->validated());

        return $this->recalculateAndRedirect($match);
    }

    public function destroy(Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $event->delete();

        return $this->recalculateAndRedirect($match, 'Evento eliminado.');
    }

    private function recalculateAndRedirect(FootballMatch $match, ?string $message = null): RedirectResponse
    {
        $this->statService->recalculateScore($match);

        if ($match->stats_finalized_at) {
            $this->statService->finalizeStats($match);
        }

        $redirect = back();

        return $message ? $redirect->with('success', $message) : $redirect;
    }
}
