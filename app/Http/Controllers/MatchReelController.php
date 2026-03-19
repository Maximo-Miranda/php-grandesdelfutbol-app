<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Services\ReelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MatchReelController extends Controller
{
    public function generate(Club $club, FootballMatch $match, ReelService $reelService): RedirectResponse
    {
        Gate::authorize('update', $match);

        if (! $match->youtube_url) {
            return back()->with('error', 'El partido no tiene video de YouTube.');
        }

        if ($match->reels()->exists()) {
            return back()->with('error', 'Los reels ya fueron generados. Elimina los existentes primero.');
        }

        $reelService->generateReelsForMatch($match);

        return back()->with('success', 'Generación de reels iniciada.');
    }

    public function destroy(Club $club, FootballMatch $match, MatchReel $reel): RedirectResponse
    {
        Gate::authorize('update', $match);

        $reel->clearMediaCollection('reel');
        $reel->delete();

        return back()->with('success', 'Reel eliminado.');
    }
}
