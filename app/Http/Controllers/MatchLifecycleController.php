<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Notifications\MatchStatsFinalizedNotification;
use App\Services\MatchStatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class MatchLifecycleController extends Controller
{
    public function __construct(private MatchStatService $statService) {}

    public function start(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Upcoming) {
            return back()->with('error', 'El partido solo puede iniciarse desde estado programado.');
        }

        if ($match->scheduled_at->subMinutes(30)->isFuture()) {
            return back()->with('error', 'El partido solo puede iniciarse 30 minutos antes.');
        }

        $match->update([
            'status' => MatchStatus::InProgress,
            'started_at' => now(),
        ]);

        return back()->with('success', 'Partido iniciado.');
    }

    public function complete(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::InProgress) {
            return back()->with('error', 'El partido solo puede completarse desde estado en progreso.');
        }

        $match->update([
            'status' => MatchStatus::Completed,
            'ended_at' => now(),
        ]);

        return back()->with('success', 'Partido completado.');
    }

    public function cancel(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status === MatchStatus::Completed) {
            return back()->with('error', 'Los partidos completados no pueden ser cancelados.');
        }

        $match->update([
            'status' => MatchStatus::Cancelled,
        ]);

        return back()->with('success', 'Partido cancelado.');
    }

    public function finalizeStats(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Completed) {
            return back()->with('error', 'Las estadísticas solo pueden registrarse para partidos completados.');
        }

        $this->statService->finalizeStats($match);

        $users = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->with('player.user')
            ->get()
            ->pluck('player.user')
            ->filter();

        Notification::send($users, new MatchStatsFinalizedNotification($match));

        return back()->with('success', 'Estadísticas registradas.');
    }
}
