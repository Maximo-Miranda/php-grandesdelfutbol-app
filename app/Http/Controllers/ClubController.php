<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Enums\ClubMemberStatus;
use App\Http\Requests\Club\StoreClubRequest;
use App\Http\Requests\Club\UpdateClubRequest;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\FootballMatch;
use App\Services\AttachmentService;
use App\Services\ClubContext;
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

    public function index(): Response|RedirectResponse
    {
        app(ClubContext::class)->clear();

        $user = auth()->user();

        $clubs = Club::query()
            ->forUser($user)
            ->with('owner')
            ->withCount([
                'members',
                'matches',
                'matches as upcoming_matches_count' => fn ($query) => $query->upcoming(),
            ])
            ->latest()
            ->get();

        $pendingMemberships = $user->clubMemberships()
            ->with('club')
            ->where('status', ClubMemberStatus::Pending)
            ->get();

        if ($clubs->isEmpty() && $pendingMemberships->isEmpty()) {
            $invitation = ClubInvitation::query()
                ->where('email', $user->email)
                ->valid()
                ->first();

            if ($invitation) {
                return redirect()->route('invitations.show', $invitation->token);
            }

            return redirect()->route('clubs.create');
        }

        $clubIds = $clubs->pluck('id');

        $nextMatch = FootballMatch::query()
            ->whereIn('club_id', $clubIds)
            ->upcoming()
            ->with('club', 'field')
            ->withCount('attendances')
            ->orderBy('scheduled_at')
            ->first();

        return Inertia::render('clubs/Index', [
            'clubs' => $clubs,
            'nextMatch' => $nextMatch,
            'pendingInvitations' => ClubInvitation::query()
                ->where('email', $user->email)
                ->valid()
                ->with('club')
                ->get(),
            'pendingMemberships' => $pendingMemberships,
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
        $club->loadCount('members', 'matches');

        $nextMatch = $club->matches()
            ->upcoming()
            ->with('field', 'attendances')
            ->orderBy('scheduled_at')
            ->first();

        return Inertia::render('clubs/Show', [
            'club' => $club,
            'nextMatch' => $nextMatch,
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
