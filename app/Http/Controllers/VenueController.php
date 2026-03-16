<?php

namespace App\Http\Controllers;

use App\Http\Requests\Venue\StoreVenueRequest;
use App\Http\Requests\Venue\UpdateVenueRequest;
use App\Models\Club;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            ->with('success', 'Sede creada.');
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
            ->with('success', 'Sede actualizada.');
    }

    public function destroy(Club $club, Venue $venue): RedirectResponse
    {
        Gate::authorize('delete', $venue);

        $venue->delete();

        return redirect()->route('clubs.venues.index', $club)
            ->with('success', 'Sede eliminada.');
    }

    public function storeQuick(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('create', [Venue::class, $club]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'map_link' => ['nullable', 'url', 'max:500'],
            'field_name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:5v5,6v6,7v7,8v8,9v9,10v10,11v11'],
            'surface_type' => ['nullable', 'string', 'max:100'],
        ]);

        $venue = $club->venues()->create(
            Arr::only($validated, ['name', 'address', 'map_link']),
        );

        $venue->fields()->create([
            'name' => $validated['field_name'],
            'field_type' => $validated['field_type'],
            'surface_type' => $validated['surface_type'] ?? null,
        ]);

        return back()->with('success', 'Cancha creada.');
    }
}
