<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClubJoinController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function show(string $token): Response|RedirectResponse
    {
        $club = Club::query()
            ->where('invite_token', $token)
            ->where('is_invite_active', true)
            ->firstOrFail();

        if (Auth::check()) {
            if (! Auth::user()->hasVerifiedEmail()) {
                redirect()->setIntendedUrl(route('clubs.join', $token));

                return redirect()->route('verification.notice');
            }

            $member = $this->invitationService->joinViaLink($club, Auth::user());

            if ($member->status->value === 'pending') {
                return redirect()->route('dashboard')
                    ->with('success', 'Tu solicitud de union ha sido enviada. El admin del club debe aprobarla.');
            }

            return redirect()->route('clubs.show', $club)
                ->with('success', 'Te has unido al club!');
        }

        redirect()->setIntendedUrl(route('clubs.join', $token));

        return Inertia::render('clubs/JoinLink', [
            'club' => $club->only('name', 'description'),
            'token' => $token,
            'requiresApproval' => $club->requires_approval,
        ]);
    }

    public function store(string $token): RedirectResponse
    {
        $club = Club::query()
            ->where('invite_token', $token)
            ->where('is_invite_active', true)
            ->firstOrFail();

        $member = $this->invitationService->joinViaLink($club, auth()->user());

        if ($member->status->value === 'pending') {
            return redirect()->route('clubs.index')
                ->with('success', 'Tu solicitud de union ha sido enviada para aprobacion.');
        }

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Te has unido al club!');
    }
}
