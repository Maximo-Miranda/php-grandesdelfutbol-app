<?php

namespace App\Http\Controllers;

use App\Enums\ReelSource;
use App\Enums\ReelStatus;
use App\Http\Requests\Match\StoreManualReelRequest;
use App\Http\Requests\Match\StoreReelRequestRequest;
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

        if ($match->reels()->where('source', ReelSource::Auto)->exists()) {
            return back()->with('error', 'Los reels automáticos ya fueron generados. Elimina los existentes primero.');
        }

        $reelService->generateReelsForMatch($match);

        return back()->with('success', 'Generación de reels iniciada.');
    }

    public function store(StoreManualReelRequest $request, Club $club, FootballMatch $match, ReelService $reelService): RedirectResponse
    {
        if (! $match->youtube_url) {
            return back()->with('error', 'El partido no tiene video de YouTube.');
        }

        $reelService->createManualClip($match, $request->validated());

        return back()->with('success', 'Clip manual creado.');
    }

    public function request(StoreReelRequestRequest $request, Club $club, FootballMatch $match, ReelService $reelService): RedirectResponse
    {
        if (! $match->youtube_url) {
            return back()->with('error', 'El partido no tiene video de YouTube.');
        }

        $reelService->createMatchClip($match, $request->validated());

        return back()->with('success', 'Reel creado.');
    }

    public function requestForPlayer(StoreReelRequestRequest $request, Club $club, FootballMatch $match, ReelService $reelService): RedirectResponse
    {
        if (! $match->youtube_url) {
            return back()->with('error', 'El partido no tiene video de YouTube.');
        }

        $reelService->createPlayerClip($match, $request->user(), $request->validated());

        return back()->with('success', 'Reel creado.');
    }

    public function approve(Club $club, FootballMatch $match, MatchReel $reel, ReelService $reelService): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($reel->status !== ReelStatus::Requested) {
            return back()->with('error', 'Este reel no está en estado de solicitud.');
        }

        $reelService->approveClipRequest($reel);

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function reject(Club $club, FootballMatch $match, MatchReel $reel, ReelService $reelService): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($reel->status !== ReelStatus::Requested) {
            return back()->with('error', 'Este reel no está en estado de solicitud.');
        }

        $reelService->rejectClipRequest($reel);

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function view(Club $club, FootballMatch $match, MatchReel $reel): RedirectResponse
    {
        $reel->increment('view_count');

        return back();
    }

    public function destroy(Club $club, FootballMatch $match, MatchReel $reel): RedirectResponse
    {
        $user = request()->user();
        $isAdmin = $club->isAdminOrOwner($user);
        $isOwner = $reel->requested_by === $user->id;

        if (! $isAdmin && ! $isOwner) {
            abort(403);
        }

        $reel->clearMediaCollection('reel');
        $reel->delete();

        return back()->with('success', 'Reel eliminado.');
    }
}
