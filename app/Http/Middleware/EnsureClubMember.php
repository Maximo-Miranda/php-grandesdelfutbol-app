<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $club = $request->route('club');

        if (! $club instanceof Club) {
            abort(404);
        }

        $membership = $club->getMembership($request->user());

        if (! $membership) {
            abort(403, 'You are not a member of this club.');
        }

        $request->merge(['clubMembership' => $membership]);

        return $next($request);
    }
}
