<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\MatchStatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MatchLifecycleController extends Controller
{
    public function __construct(private MatchStatService $statService) {}

    public function start(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Upcoming) {
            return back()->with('error', 'Match can only be started from upcoming status.');
        }

        $match->update([
            'status' => MatchStatus::InProgress,
            'started_at' => now(),
        ]);

        return back()->with('success', 'Match started.');
    }

    public function complete(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::InProgress) {
            return back()->with('error', 'Match can only be completed from in-progress status.');
        }

        $match->update([
            'status' => MatchStatus::Completed,
            'ended_at' => now(),
        ]);

        return back()->with('success', 'Match completed.');
    }

    public function cancel(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status === MatchStatus::Completed) {
            return back()->with('error', 'Completed matches cannot be cancelled.');
        }

        $match->update([
            'status' => MatchStatus::Cancelled,
        ]);

        return back()->with('success', 'Match cancelled.');
    }

    public function finalizeStats(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        if ($match->status !== MatchStatus::Completed) {
            return back()->with('error', 'Stats can only be finalized for completed matches.');
        }

        if ($match->stats_finalized_at) {
            return back()->with('error', 'Stats have already been finalized.');
        }

        $this->statService->finalizeStats($match);

        return back()->with('success', 'Stats finalized.');
    }
}
