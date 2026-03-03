<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\User;
use App\Services\ClubContext;
use App\Services\ClubService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class SetClubContext
{
    public function __construct(
        private ClubContext $clubContext,
        private ClubService $clubService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $club = ($request->session()->get('active_club_id') ?? $user->last_club_id)
            |> (fn (?int $id) => $this->clubService->resolveForUser($user, $id));

        if (! $club) {
            return $this->handleNoClub($request, $next, $user);
        }

        $this->ensureClubIsActive($request, $club, $user);

        return $next($request);
    }

    private function handleNoClub(Request $request, Closure $next, User $user): Response
    {
        $request->session()->forget('active_club_id');

        if ($user->last_club_id) {
            $user->update(['last_club_id' => null]);
        }

        if (! $this->isClubIndependentRoute($request)) {
            return redirect()->route('clubs.create');
        }

        return $next($request);
    }

    private function ensureClubIsActive(Request $request, Club $club, User $user): void
    {
        $this->clubContext->set($club);

        if ($club->id !== $request->session()->get('active_club_id')) {
            $this->clubService->switchToClub($user, $club);
        }
    }

    private function isClubIndependentRoute(Request $request): bool
    {
        if ($request->route('club')) {
            return true;
        }

        return $request->routeIs(
            'clubs.create',
            'clubs.store',
            'invitations.*',
            'clubs.join',
            'clubs.join.store',
            'profile.*',
            'user-password.*',
            'appearance.*',
            'two-factor.*',
            'player-profile.*',
            'dashboard',
            'logout',
            'verification.*',
            'password.*',
        );
    }
}
