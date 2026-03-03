<?php

namespace App\Http\Controllers;

use App\Enums\PlayerPosition;
use App\Http\Requests\Player\StorePlayerRequest;
use App\Http\Requests\Player\UpdatePlayerRequest;
use App\Models\Club;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
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
            'players' => $club->players()->with('user')->get(),
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
            ->with('success', 'Player added.');
    }

    public function show(Club $club, Player $player): Response
    {
        Gate::authorize('view', $player);

        return Inertia::render('clubs/players/Show', [
            'club' => $club,
            'player' => $player->load('user'),
        ]);
    }

    public function edit(Club $club, Player $player): Response
    {
        Gate::authorize('update', $player);

        return Inertia::render('clubs/players/Edit', [
            'club' => $club,
            'player' => $player,
            'positions' => collect(PlayerPosition::cases())->map(fn (PlayerPosition $p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function update(UpdatePlayerRequest $request, Club $club, Player $player): RedirectResponse
    {
        $player->update($request->validated());

        return redirect()->route('clubs.players.show', [$club, $player])
            ->with('success', 'Player updated.');
    }
}
