<?php

namespace App\Http\Controllers;

use App\Http\Requests\Venue\StoreVenueRequest;
use App\Http\Requests\Venue\UpdateVenueRequest;
use App\Models\Club;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function index(Club $club): Response
    {
        Gate::authorize('viewAny', [Venue::class, $club]);

        return Inertia::render('clubs/venues/Index', [
            'club' => $club,
            'venues' => $club->venues()->with('fields')->get(),
        ]);
    }

    public function create(Club $club): Response
    {
        Gate::authorize('create', [Venue::class, $club]);

        return Inertia::render('clubs/venues/Create', [
            'club' => $club,
        ]);
    }

    public function store(StoreVenueRequest $request, Club $club): RedirectResponse
    {
        $club->venues()->create($request->validated());

        return redirect()->route('clubs.venues.index', $club)
            ->with('success', 'Venue created.');
    }

    public function show(Club $club, Venue $venue): Response
    {
        Gate::authorize('view', $venue);

        return Inertia::render('clubs/venues/Show', [
            'club' => $club,
            'venue' => $venue->load('fields'),
        ]);
    }

    public function edit(Club $club, Venue $venue): Response
    {
        Gate::authorize('update', $venue);

        return Inertia::render('clubs/venues/Edit', [
            'club' => $club,
            'venue' => $venue,
        ]);
    }

    public function update(UpdateVenueRequest $request, Club $club, Venue $venue): RedirectResponse
    {
        $venue->update($request->validated());

        return redirect()->route('clubs.venues.show', [$club, $venue])
            ->with('success', 'Venue updated.');
    }
}
