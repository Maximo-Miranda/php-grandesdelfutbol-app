<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchEventController extends Controller
{
    public function store(Request $request, Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'player_id' => ['required', 'exists:players,id'],
            'event_type' => ['required', 'string', 'in:goal,assist,yellow_card,red_card,penalty_scored,penalty_missed,free_kick,save,own_goal,substitution,injury,foul'],
            'minute' => ['required', 'integer', 'min:0', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $match->events()->create($validated);

        return back()->with('success', 'Evento registrado.');
    }

    public function destroy(Club $club, FootballMatch $match, MatchEvent $event): RedirectResponse
    {
        Gate::authorize('update', $match);

        $event->delete();

        return back()->with('success', 'Evento eliminado.');
    }
}
