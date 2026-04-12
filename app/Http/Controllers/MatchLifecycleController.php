<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Notifications\MatchStatsFinalizedNotification;
use App\Services\MatchService;
use App\Services\MatchStatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class MatchLifecycleController extends Controller
{
    public function __construct(
        private readonly MatchStatService $statService,
        private readonly MatchService $matchService,
    ) {}

    public function start(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Upcoming) {
            return back()->with('error', 'El partido solo puede iniciarse desde estado programado.');
        }

        if ($match->scheduled_at->subMinutes(30)->isFuture()) {
            return back()->with('error', 'El partido solo puede iniciarse 30 minutos antes.');
        }

        if (! $this->transitionStatus($match, MatchStatus::Upcoming, [
            'status' => MatchStatus::InProgress,
            'started_at' => now(),
        ])) {
            return back()->with('error', 'El partido ya fue modificado por otro proceso.');
        }

        return back()->with('success', 'Partido iniciado.');
    }

    public function complete(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::InProgress) {
            return back()->with('error', 'El partido solo puede completarse desde estado en progreso.');
        }

        if (! $this->transitionStatus($match, MatchStatus::InProgress, [
            'status' => MatchStatus::Completed,
            'ended_at' => now(),
        ])) {
            return back()->with('error', 'El partido ya fue modificado por otro proceso.');
        }

        $match->status = MatchStatus::Completed;
        $this->matchService->recreateIfRecurring($match);

        return back()->with('success', 'Partido completado.');
    }

    public function cancel(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status === MatchStatus::Completed) {
            return back()->with('error', 'Los partidos completados no pueden ser cancelados.');
        }

        if (! $this->transitionStatus($match, $match->status, [
            'status' => MatchStatus::Cancelled,
        ])) {
            return back()->with('error', 'El partido ya fue modificado por otro proceso.');
        }

        $match->status = MatchStatus::Cancelled;
        $this->matchService->recreateIfRecurring($match);

        return back()->with('success', 'Partido cancelado.');
    }

    public function finalizeStats(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Completed) {
            return back()->with('error', 'Las estadísticas solo pueden registrarse para partidos completados.');
        }

        $isFirstFinalization = $match->stats_finalized_at === null;

        $this->statService->finalizeStats($match);

        if ($isFirstFinalization) {
            Notification::send($club->approvedMemberUsers(), new MatchStatsFinalizedNotification($match));
        }

        return back()->with('success', 'Estadísticas registradas.');
    }

    public function updateScore(Request $request, Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Completed) {
            return back()->with('error', 'El marcador solo puede registrarse para partidos completados.');
        }

        $validated = $request->validate([
            'team_a_score' => ['required', 'integer', 'min:0', 'max:99'],
            'team_b_score' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $match->update($validated);

        return back()->with('success', 'Marcador actualizado.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transitionStatus(FootballMatch $match, MatchStatus $expectedStatus, array $data): bool
    {
        return FootballMatch::query()
            ->where('id', $match->id)
            ->where('status', $expectedStatus)
            ->update($data) > 0;
    }
}
