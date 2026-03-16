<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Notifications\MemberApprovedNotification;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MemberRemovedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ClubMemberController extends Controller
{
    public function index(Request $request, Club $club): Response
    {
        Gate::authorize('view', $club);

        $isAdmin = $club->isAdminOrOwner($request->user());
        $search = $request->query('search', '');

        $membersQuery = $club->members()
            ->with('user.playerProfile')
            ->where('status', ClubMemberStatus::Approved);

        if ($search !== '') {
            $membersQuery->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('clubs/Members', [
            'club' => $club,
            'search' => $search,
            'pendingMembers' => $club->members()
                ->with('user.playerProfile')
                ->where('status', ClubMemberStatus::Pending)
                ->latest()
                ->get(),
            'members' => Inertia::scroll(
                fn () => $membersQuery
                    ->latest('approved_at')
                    ->simplePaginate(20, pageName: 'members'),
            ),
            'invitations' => $isAdmin
                ? Inertia::scroll(
                    fn () => $club->invitations()
                        ->with('inviter')
                        ->latest()
                        ->simplePaginate(15, pageName: 'invitations'),
                )
                : [],
        ]);
    }

    public function approve(Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('update', $club);

        $member->update([
            'status' => ClubMemberStatus::Approved,
            'approved_at' => now(),
        ]);

        Player::query()->firstOrCreate(
            ['club_id' => $club->id, 'user_id' => $member->user_id],
            ['name' => $member->user->name, 'is_active' => true],
        );

        $member->user->notify(new MemberApprovedNotification($club));

        return back()->with('success', 'Miembro aprobado.');
    }

    public function reject(Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('update', $club);

        $member->delete();

        return back()->with('success', 'Solicitud de miembro rechazada.');
    }

    public function updateRole(Request $request, Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('updateRole', $member);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,player'],
        ]);

        $actorMembership = $club->getMembership($request->user());

        // Only Owner can promote to admin
        if ($validated['role'] === 'admin' && ! $actorMembership->isOwner()) {
            abort(403, 'Solo el dueño puede promover a admin.');
        }

        $member->update(['role' => ClubMemberRole::from($validated['role'])]);

        return back()->with('success', 'Rol de miembro actualizado.');
    }

    public function remove(Club $club, ClubMember $member): RedirectResponse
    {
        Gate::authorize('remove', $member);

        $member->user->notify(new MemberRemovedNotification($club));
        $member->delete();

        return back()->with('success', 'Miembro expulsado.');
    }

    public function leave(Request $request, Club $club): RedirectResponse
    {
        $membership = $club->getMembership($request->user());

        if (! $membership) {
            abort(403);
        }

        Gate::authorize('leave', $membership);

        $user = $request->user();
        $membership->delete();

        Notification::send(
            $club->adminUsers(),
            new MemberLeftNotification($club, $user),
        );

        return redirect()->route('clubs.index')->with('success', 'Has salido del club.');
    }
}
