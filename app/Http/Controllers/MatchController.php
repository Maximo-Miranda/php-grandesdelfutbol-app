<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Http\Requests\Match\UpdateMatchRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\MatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(private MatchService $matchService) {}

    public function index(Request $request, Club $club): Response
    {
        Gate::authorize('viewAny', [FootballMatch::class, $club]);

        $filter = $request->enum('filter', MatchStatus::class);

        $query = $club->matches()
            ->with('field')
            ->withCount('attendances');

        if ($filter === MatchStatus::Upcoming) {
            $query->whereIn('status', [MatchStatus::Upcoming, MatchStatus::InProgress])
                ->orderBy('scheduled_at');
        } elseif ($filter === MatchStatus::Completed) {
            $query->where('status', MatchStatus::Completed)
                ->orderByDesc('scheduled_at');
        } else {
            $query->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                MatchStatus::Upcoming->value,
                MatchStatus::InProgress->value,
            ])
                ->orderByRaw('CASE WHEN status IN (?, ?) THEN scheduled_at END ASC', [
                    MatchStatus::Upcoming->value,
                    MatchStatus::InProgress->value,
                ])
                ->orderByRaw('CASE WHEN status NOT IN (?, ?) THEN scheduled_at END DESC', [
                    MatchStatus::Upcoming->value,
                    MatchStatus::InProgress->value,
                ]);
        }

        return Inertia::render('clubs/matches/Index', [
            'club' => $club,
            'filter' => $filter?->value ?? 'all',
            'matches' => Inertia::scroll(fn () => $query->simplePaginate(15)),
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
            ->with('success', 'Partido creado.');
    }

    public function show(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('view', $match);

        $user = request()->user();
        $match->load('field.venue', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile');

        $member = $club->members()->where('user_id', $user->id)->first();
        $isAdmin = $member && in_array($member->role->value, ['owner', 'admin']);

        if ($match->status === MatchStatus::InProgress && $isAdmin) {
            return Inertia::render('clubs/matches/Live', [
                'club' => $club,
                'match' => $match,
                'players' => $club->players()->active()->with('user.playerProfile')->get(),
            ]);
        }

        if ($match->status === MatchStatus::Completed) {
            return Inertia::render('clubs/matches/Summary', [
                'club' => $club,
                'match' => $match,
                'isAdmin' => $isAdmin,
                'players' => $isAdmin
                    ? $club->players()->active()->with('user.playerProfile')->get()
                    : [],
            ]);
        }

        $myPlayer = $club->players()->where('user_id', $user->id)->first();

        $registeredPlayerIds = $match->attendances()->pluck('player_id');

        return Inertia::render('clubs/matches/Show', [
            'club' => $club,
            'match' => $match,
            'players' => $club->players()->active()->with('user.playerProfile')->get(),
            'isAdmin' => $isAdmin,
            'myPlayer' => $myPlayer,
            'unregisteredPlayers' => $isAdmin
                ? Inertia::scroll(
                    fn () => $club->players()
                        ->active()
                        ->with('user.playerProfile')
                        ->whereNotIn('id', $registeredPlayerIds)
                        ->orderBy('name')
                        ->simplePaginate(15, pageName: 'jugadores'),
                )
                : null,
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
            ->with('success', 'Partido actualizado.');
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

        $match->load('field', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile');

        return Inertia::render('clubs/matches/Live', [
            'club' => $club,
            'match' => $match,
            'players' => $club->players()->active()->with('user.playerProfile')->get(),
        ]);
    }

    public function summary(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('view', $match);

        $user = request()->user();
        $match->load('field.venue', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile');

        $member = $club->members()->where('user_id', $user->id)->first();
        $isAdmin = $member && in_array($member->role->value, ['owner', 'admin']);

        return Inertia::render('clubs/matches/Summary', [
            'club' => $club,
            'match' => $match,
            'isAdmin' => $isAdmin,
            'players' => $isAdmin
                ? $club->players()->active()->with('user.playerProfile')->get()
                : [],
        ]);
    }
}
