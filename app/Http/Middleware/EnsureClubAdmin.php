<?php

namespace App\Http\Middleware;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $club = $request->route('club');

        if (! $club instanceof Club) {
            abort(404);
        }

        $membership = $club->members()
            ->where('user_id', $request->user()->id)
            ->where('status', ClubMemberStatus::Approved)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Owner])
            ->first();

        if (! $membership) {
            abort(403, 'You must be an admin to perform this action.');
        }

        $request->merge(['clubMembership' => $membership]);

        return $next($request);
    }
}
