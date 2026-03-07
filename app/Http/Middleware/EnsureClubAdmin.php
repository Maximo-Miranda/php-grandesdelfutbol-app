<?php

namespace App\Http\Middleware;

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

        $membership = $club->getMembership($request->user());

        if (! $membership || ! $membership->isAtLeastAdmin()) {
            abort(403, 'You must be an admin to perform this action.');
        }

        $request->merge(['clubMembership' => $membership]);

        return $next($request);
    }
}
