<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use Inertia\Inertia;
use Inertia\Response;

class PublicMatchController extends Controller
{
    public function show(string $shareToken): Response
    {
        $match = FootballMatch::query()
            ->where('share_token', $shareToken)
            ->with('club', 'field', 'attendances.player', 'events.player')
            ->firstOrFail();

        return Inertia::render('matches/Public', [
            'match' => $match,
        ]);
    }
}
