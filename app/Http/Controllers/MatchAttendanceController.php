<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Services\MatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchAttendanceController extends Controller
{
    public function __construct(private MatchService $matchService) {}

    public function store(Request $request, Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('register', $match);

        $validated = $request->validate([
            'player_id' => ['required', 'exists:players,id'],
            'status' => ['required', 'string', 'in:confirmed,declined'],
            'team' => ['nullable', 'string', 'in:a,b'],
        ]);

        $player = $club->players()->findOrFail($validated['player_id']);

        $team = isset($validated['team']) ? AttendanceTeam::from($validated['team']) : null;

        $this->matchService->registerPlayer(
            $match,
            $player,
            AttendanceStatus::from($validated['status']),
            $team,
        );

        return back()->with('success', 'Registration updated.');
    }

    public function update(Request $request, Club $club, FootballMatch $match, MatchAttendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:confirmed,declined'],
            'team' => ['nullable', 'string', 'in:a,b'],
            'role' => ['nullable', 'string', 'in:pending,starter,substitute'],
        ]);

        if (isset($validated['status'])) {
            $status = AttendanceStatus::from($validated['status']);

            if ($status === AttendanceStatus::Declined) {
                $attendance->update([
                    'status' => $status,
                    'role' => AttendanceRole::Pending,
                    'team' => null,
                    'confirmed_at' => null,
                ]);
            } else {
                $confirmedCount = $match->attendances()
                    ->where('status', AttendanceStatus::Confirmed)
                    ->where('id', '!=', $attendance->id)
                    ->count();

                $role = $confirmedCount < $match->max_players
                    ? AttendanceRole::Starter
                    : AttendanceRole::Substitute;

                $attendance->update([
                    'status' => $status,
                    'role' => $role,
                    'confirmed_at' => now(),
                ]);
            }

            return back()->with('success', 'Attendance updated.');
        }

        $data = [];
        if (isset($validated['team'])) {
            $data['team'] = AttendanceTeam::from($validated['team']);
        }
        if (isset($validated['role'])) {
            $data['role'] = $validated['role'];
        }

        $attendance->update($data);

        return back()->with('success', 'Attendance updated.');
    }

    public function destroy(Club $club, FootballMatch $match, MatchAttendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $match);

        $attendance->delete();

        return back()->with('success', 'Player removed from match.');
    }

    public function autoAssign(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        $this->matchService->autoAssignTeams($match);

        return back()->with('success', 'Teams auto-assigned.');
    }
}
