<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClubJoinController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function show(string $token): Response
    {
        $club = Club::query()
            ->where('invite_token', $token)
            ->where('is_invite_active', true)
            ->firstOrFail();

        return Inertia::render('clubs/Join', [
            'club' => $club->only('id', 'name', 'description'),
            'token' => $token,
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
                ->with('success', 'Tu solicitud de unión ha sido enviada para aprobación.');
        }

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Te has unido al club!');
    }
}
