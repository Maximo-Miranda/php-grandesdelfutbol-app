<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClubInvitation\StoreInvitationRequest;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClubInvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function create(Club $club): Response
    {
        return Inertia::render('clubs/Invite', [
            'club' => $club,
            'invitations' => $club->invitations()->with('inviter')->latest()->get(),
        ]);
    }

    public function store(StoreInvitationRequest $request, Club $club): RedirectResponse
    {
        $this->invitationService->sendInvitation(
            $club,
            $request->validated('email'),
            $request->user(),
        );

        return back()->with('success', 'Invitation sent successfully.');
    }

    public function accept(string $token): RedirectResponse
    {
        $invitation = ClubInvitation::query()
            ->valid()
            ->where('token', $token)
            ->firstOrFail();

        $user = auth()->user();

        $this->invitationService->acceptInvitation($invitation, $user);

        return redirect()->route('clubs.show', $invitation->club_id)
            ->with('success', 'You have joined the club!');
    }
}
