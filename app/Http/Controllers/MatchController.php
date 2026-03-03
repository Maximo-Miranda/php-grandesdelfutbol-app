<?php

namespace App\Http\Controllers;

use App\Http\Requests\Match\StoreMatchRequest;
use App\Http\Requests\Match\UpdateMatchRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\MatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(private MatchService $matchService) {}

    public function index(Club $club): Response
    {
        Gate::authorize('viewAny', [FootballMatch::class, $club]);

        return Inertia::render('clubs/matches/Index', [
            'club' => $club,
            'matches' => $club->matches()
                ->with('field')
                ->withCount('attendances')
                ->latest('scheduled_at')
                ->get(),
        ]);
    }

    public function create(Club $club): Response
    {
        Gate::authorize('create', [FootballMatch::class, $club]);

        return Inertia::render('clubs/matches/Create', [
            'club' => $club,
            'venues' => $club->venues()->with('fields')->where('is_active', true)->get(),
        ]);
    }

    public function store(StoreMatchRequest $request, Club $club): RedirectResponse
    {
        $match = $this->matchService->createMatch($club, $request->validated());

        return redirect()->route('clubs.matches.show', [$club, $match])
            ->with('success', 'Match created.');
    }

    public function show(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('view', $match);

        $user = request()->user();
        $match->load('field.venue', 'attendances.player', 'events.player');

        $member = $club->members()->where('user_id', $user->id)->first();
        $myPlayer = $club->players()->where('user_id', $user->id)->first();

        return Inertia::render('clubs/matches/Show', [
            'club' => $club,
            'match' => $match,
            'players' => $club->players()->active()->get(),
            'isAdmin' => $member && in_array($member->role->value, ['owner', 'admin']),
            'myPlayer' => $myPlayer,
        ]);
    }

    public function edit(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('update', $match);

        return Inertia::render('clubs/matches/Edit', [
            'club' => $club,
            'match' => $match,
            'venues' => $club->venues()->with('fields')->where('is_active', true)->get(),
        ]);
    }

    public function update(UpdateMatchRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $match->update($request->validated());

        return redirect()->route('clubs.matches.show', [$club, $match])
            ->with('success', 'Match updated.');
    }

    public function destroy(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('delete', $match);

        $match->delete();

        return redirect()->route('clubs.matches.index', $club)
            ->with('success', 'Partido eliminado.');
    }

    public function live(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('update', $match);

        $match->load('field', 'attendances.player', 'events.player');

        return Inertia::render('clubs/matches/Live', [
            'club' => $club,
            'match' => $match,
            'players' => $club->players()->active()->get(),
        ]);
    }

    public function summary(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('view', $match);

        $match->load('field', 'attendances.player', 'events.player');

        return Inertia::render('clubs/matches/Summary', [
            'club' => $club,
            'match' => $match,
        ]);
    }
}
