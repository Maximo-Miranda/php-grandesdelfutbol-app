<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClubJoinController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function show(string $slug): Response|RedirectResponse
    {
        $club = Club::query()->where('slug', $slug)->firstOrFail();

        if (! Auth::check()) {
            redirect()->setIntendedUrl(route('clubs.join', $slug));

            return Inertia::render('clubs/JoinLink', [
                'club' => $club->only('name', 'description'),
                'slug' => $slug,
            ]);
        }

        if (! Auth::user()->hasVerifiedEmail()) {
            redirect()->setIntendedUrl(route('clubs.join', $slug));

            return redirect()->route('verification.notice');
        }

        $member = $this->invitationService->joinViaLink($club, Auth::user());

        if ($member->status === ClubMemberStatus::Pending) {
            return Inertia::render('clubs/JoinPending', [
                'club' => $club->only('name', 'description'),
                'isNewRequest' => $member->wasRecentlyCreated,
            ]);
        }

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Te has unido al club!');
    }

    public function store(string $slug): RedirectResponse
    {
        $club = Club::query()->where('slug', $slug)->firstOrFail();

        $member = $this->invitationService->joinViaLink($club, Auth::user());

        if ($member->status === ClubMemberStatus::Pending) {
            return redirect()->route('clubs.join', $slug);
        }

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Te has unido al club!');
    }
}
