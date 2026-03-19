<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Scopes\ClubScope;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PublicMatchController extends Controller
{
    public function show(string $shareToken): Response
    {
        $match = FootballMatch::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('share_token', $shareToken)
            ->with('club', 'field', 'attendances.player', 'events.player', 'events.relatedPlayer')
            ->firstOrFail();

        $user = Auth::user();
        $isMember = $user && $match->club->members()->where('user_id', $user->id)->exists();

        return Inertia::render('matches/Public', [
            'match' => $match,
            'isMember' => $isMember,
        ]);
    }
}
