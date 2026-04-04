<?php

namespace App\Http\Controllers;

use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Http\Requests\Match\StoreManualReelRequest;
use App\Http\Requests\Match\StoreReelRequestRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Services\ReelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchReelController extends Controller
{
    public function __construct(private ReelService $reelService) {}

    public function generate(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);
        $this->ensureHasVideo($match);

        $this->reelService->generateReelsForMatch($match);

        return back()->with('success', 'Generación de reels iniciada.');
    }

    public function store(StoreManualReelRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $this->ensureHasVideo($match);

        $this->reelService->createManualClip($match, $request->validated());

        return back()->with('success', 'Clip manual creado.');
    }

    public function request(StoreReelRequestRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $this->ensureHasVideo($match);

        $this->reelService->createMatchClip($match, $request->validated());

        return back()->with('success', 'Reel creado.');
    }

    public function requestForPlayer(StoreReelRequestRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $this->ensureHasVideo($match);

        $this->reelService->createPlayerClip($match, $request->user(), $request->validated());

        return back()->with('success', 'Reel creado.');
    }

    public function approve(Club $club, FootballMatch $match, MatchReel $reel): RedirectResponse
    {
        Gate::authorize('update', $match);
        $this->ensureReelIsRequested($reel);

        $this->reelService->approveClipRequest($reel);

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function reject(Club $club, FootballMatch $match, MatchReel $reel): RedirectResponse
    {
        Gate::authorize('update', $match);
        $this->ensureReelIsRequested($reel);

        $this->reelService->rejectClipRequest($reel);

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function view(Club $club, FootballMatch $match, MatchReel $reel): JsonResponse
    {
        $reel->increment('view_count');

        return response()->json(['view_count' => $reel->view_count]);
    }

    public function destroy(Request $request, Club $club, FootballMatch $match, MatchReel $reel): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $club->isAdminOrOwner($user)
            || $reel->requested_by === $user->id
            || $reel->player?->user_id === $user->id,
            403,
        );

        $reel->clearMediaCollection('reel');
        $reel->delete();

        return back()->with('success', 'Reel eliminado.');
    }

    private function ensureHasVideo(FootballMatch $match): void
    {
        $videoUpload = $match->videoUpload;

        if (! $videoUpload || $videoUpload->status !== VideoUploadStatus::Ready) {
            abort(back()->with('error', 'El partido no tiene un video listo.'));
        }
    }

    private function ensureReelIsRequested(MatchReel $reel): void
    {
        if ($reel->status !== ReelStatus::Requested) {
            abort(back()->with('error', 'Este reel no está en estado de solicitud.'));
        }
    }
}
