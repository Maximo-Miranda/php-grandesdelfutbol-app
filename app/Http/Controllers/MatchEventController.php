<?php

namespace App\Http\Controllers;

use App\Http\Requests\Match\StoreMatchEventRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchEventController extends Controller
{
    public function store(StoreMatchEventRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $match->events()->create($request->validated());

        return back();
    }

    public function update(Request $request, Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'player_id' => ['nullable', 'exists:players,id'],
            'highlighted' => ['sometimes', 'boolean'],
        ]);

        $event->update($validated);

        return back();
    }

    public function destroy(Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $event->delete();

        return back()->with('success', 'Evento eliminado.');
    }
}
