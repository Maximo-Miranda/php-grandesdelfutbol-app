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

        $clubId = $request->session()->get('active_club_id') ?? $user->last_club_id;
        $club = $this->clubService->resolveForUser($user, $clubId);

        if (! $club) {
            $this->clearStaleContext($request, $user);

            if (! $this->isClubIndependentRoute($request)) {
                return redirect()->route('clubs.create');
            }

            return $next($request);
        }

        if ($club->id !== $request->session()->get('active_club_id')) {
            $this->activateClub($club, $user);
        } else {
            $this->clubContext->set($club);
        }

        return $next($request);
    }

    private function activateClub(Club $club, User $user): void
    {
        $this->clubContext->set($club);
        $this->clubService->switchToClub($user, $club);
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

    private function clearStaleContext(Request $request, User $user): void
    {
        $request->session()->forget('active_club_id');

        if ($user->last_club_id) {
            $user->update(['last_club_id' => null]);
        }
    }
}
