<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Notifications\MatchStatsFinalizedNotification;
use App\Services\MatchService;
use App\Services\MatchStatService;
use Illuminate\Http\RedirectResponse;
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

        $affected = FootballMatch::query()
            ->where('id', $match->id)
            ->where('status', MatchStatus::Upcoming)
            ->update([
                'status' => MatchStatus::InProgress,
                'started_at' => now(),
            ]);

        if ($affected === 0) {
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

        $affected = FootballMatch::query()
            ->where('id', $match->id)
            ->where('status', MatchStatus::InProgress)
            ->update([
                'status' => MatchStatus::Completed,
                'ended_at' => now(),
            ]);

        if ($affected === 0) {
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

        $affected = FootballMatch::query()
            ->where('id', $match->id)
            ->where('status', $match->status)
            ->update([
                'status' => MatchStatus::Cancelled,
            ]);

        if ($affected === 0) {
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
            $members = $club->approvedMemberUsers();
            if ($members->isNotEmpty()) {
                Notification::send($members, new MatchStatsFinalizedNotification($match));
            }
        }

        return back()->with('success', 'Estadísticas registradas.');
    }
}
