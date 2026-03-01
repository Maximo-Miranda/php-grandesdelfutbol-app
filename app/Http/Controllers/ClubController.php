<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Http\Requests\Club\StoreClubRequest;
use App\Http\Requests\Club\UpdateClubRequest;
use App\Models\Club;
use App\Services\AttachmentService;
use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function __construct(
        private readonly ClubService $clubService,
        private readonly AttachmentService $attachmentService,
    ) {}

    public function index(): Response
    {
        $clubs = Club::query()
            ->forUser(auth()->user())
            ->with('owner')
            ->withCount('members')
            ->latest()
            ->get();

        return Inertia::render('clubs/Index', [
            'clubs' => $clubs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clubs/Create');
    }

    public function store(StoreClubRequest $request): RedirectResponse
    {
        $club = $this->clubService->createClub(
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('clubs.show', $club);
    }

    public function show(Club $club): Response
    {
        Gate::authorize('view', $club);

        $club->load('owner', 'members.user');
        $club->loadCount('members');

        return Inertia::render('clubs/Show', [
            'club' => $club,
        ]);
    }

    public function edit(Club $club): Response
    {
        Gate::authorize('update', $club);

        return Inertia::render('clubs/Edit', [
            'club' => $club,
        ]);
    }

    public function update(UpdateClubRequest $request, Club $club): RedirectResponse
    {
        $club->update($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $this->attachmentService->upload($club, $request->file('logo'), AttachmentCollection::Logo);
        }

        return redirect()->route('clubs.show', $club);
    }
}
