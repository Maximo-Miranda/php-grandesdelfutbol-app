<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClubMemberController extends Controller
{
    public function index(Club $club): Response
    {
        Gate::authorize('view', $club);

        return Inertia::render('clubs/Members', [
            'club' => $club,
            'members' => $club->members()->with('user')->get(),
        ]);
    }

    public function approve(Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('update', $club);

        $member->update([
            'status' => ClubMemberStatus::Approved,
            'approved_at' => now(),
        ]);

        // Auto-create player record
        $user = $member->user;
        if ($user) {
            Player::query()->firstOrCreate(
                ['club_id' => $club->id, 'user_id' => $user->id],
                ['name' => $user->name, 'is_active' => true],
            );
        }

        return back()->with('success', 'Member approved.');
    }

    public function reject(Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('update', $club);

        $member->delete();

        return back()->with('success', 'Member request rejected.');
    }

    public function updateRole(Request $request, Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('update', $club);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,player'],
        ]);

        $member->update(['role' => ClubMemberRole::from($validated['role'])]);

        return back()->with('success', 'Member role updated.');
    }

    public function remove(Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('update', $club);

        if ($member->role === ClubMemberRole::Owner) {
            return back()->with('error', 'Cannot remove the club owner.');
        }

        $member->delete();

        return back()->with('success', 'Member removed.');
    }
}
