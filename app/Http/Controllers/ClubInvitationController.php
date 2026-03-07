<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Enums\InvitationStatus;
use App\Http\Requests\ClubInvitation\StoreInvitationRequest;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
            'pendingMembers' => $club->members()
                ->with('user')
                ->where('status', ClubMemberStatus::Pending)
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreInvitationRequest $request, Club $club): RedirectResponse
    {
        $this->invitationService->sendInvitation(
            $club,
            $request->validated('email'),
            $request->user(),
        );

        return back()->with('success', 'Invitación enviada.');
    }

    /**
     * Public landing page for invitation acceptance.
     * If user is logged in, auto-accepts and redirects.
     * Handles already-accepted invitations (redirect after register).
     */
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = ClubInvitation::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->with('club', 'inviter')
            ->firstOrFail();

        if (Auth::check()) {
            if ($invitation->status === InvitationStatus::Pending) {
                $this->invitationService->acceptInvitation($invitation, Auth::user());
            }

            return redirect()->route('clubs.show', $invitation->club)
                ->with('success', 'Te has unido al club!');
        }

        // Store intended URL so after login/register, user returns here
        redirect()->setIntendedUrl(route('invitations.show', $token));

        return Inertia::render('clubs/AcceptInvitation', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'club' => $invitation->club->only('id', 'name', 'description'),
                'inviter' => $invitation->inviter?->only('name'),
            ],
        ]);
    }

    /**
     * Process invitation acceptance (requires auth).
     */
    public function accept(string $token): RedirectResponse
    {
        $invitation = ClubInvitation::query()
            ->valid()
            ->where('token', $token)
            ->firstOrFail();

        $invitation->load('club');

        $this->invitationService->acceptInvitation($invitation, auth()->user());

        return redirect()->route('clubs.show', $invitation->club)
            ->with('success', 'Te has unido al club!');
    }
}
