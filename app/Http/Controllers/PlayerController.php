<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\MatchEventType;
use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Http\Requests\Player\StorePlayerRequest;
use App\Http\Requests\Player\UpdatePlayerRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Club $club): Response
    {
        Gate::authorize('viewAny', [Player::class, $club]);

        return Inertia::render('clubs/players/Index', [
            'club' => $club,
            'players' => Inertia::scroll(
                fn () => $club->players()
                    ->with('user.playerProfile')
                    ->orderByRaw('(COALESCE(goals, 0) + COALESCE(assists, 0)) DESC')
                    ->simplePaginate(20),
            ),
        ]);
    }

    public function create(Club $club): Response
    {
        Gate::authorize('create', [Player::class, $club]);

        return Inertia::render('clubs/players/Create', [
            'club' => $club,
            'positions' => collect(PlayerPosition::cases())->map(fn (PlayerPosition $p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function store(StorePlayerRequest $request, Club $club): RedirectResponse
    {
        $club->players()->create($request->validated());

        return redirect()->route('clubs.players.index', $club)
            ->with('success', 'Jugador agregado.');
    }

    public function show(Club $club, Player $player): Response
    {
        Gate::authorize('view', $player);

        $player->load('user.playerProfile');

        $lastGoal = MatchEvent::where('player_id', $player->id)
            ->whereIn('event_type', [MatchEventType::Goal, MatchEventType::PenaltyScored])
            ->with('match:id,ulid,title,scheduled_at')
            ->latest('created_at')
            ->first();

        $totalMatches = FootballMatch::where('club_id', $club->id)
            ->where('status', MatchStatus::Completed)
            ->count();

        $attendedMatches = MatchAttendance::where('player_id', $player->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->where('status', AttendanceStatus::Confirmed)
            ->count();

        return Inertia::render('clubs/players/Show', [
            'club' => $club,
            'player' => $player,
            'canEdit' => Gate::allows('update', $player),
            'lastGoal' => $lastGoal ? [
                'match_ulid' => $lastGoal->match->ulid,
                'match_title' => $lastGoal->match->title,
                'match_date' => $lastGoal->match->scheduled_at->format('d M Y'),
                'minute' => $lastGoal->minute,
            ] : null,
            'attendanceRate' => $totalMatches > 0
                ? round(($attendedMatches / $totalMatches) * 100)
                : null,
        ]);
    }

    public function edit(Request $request, Club $club, Player $player): Response
    {
        Gate::authorize('update', $player);

        return Inertia::render('clubs/players/Edit', [
            'club' => $club,
            'player' => $player,
            'positions' => collect(PlayerPosition::cases())->map(fn (PlayerPosition $p) => ['value' => $p->value, 'label' => $p->label()]),
            'isAdmin' => $player->club->isAdminOrOwner($request->user()),
        ]);
    }

    public function update(UpdatePlayerRequest $request, Club $club, Player $player): RedirectResponse
    {
        $player->update($request->validated());

        return redirect()->route('clubs.players.show', [$club, $player])
            ->with('success', 'Jugador actualizado.');
    }
}
