<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Exceptions\MatchFullException;
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
        $ability = $match->status === MatchStatus::Completed ? 'update' : 'register';
        Gate::authorize($ability, $match);

        $validated = $request->validate([
            'player_id' => ['required', 'exists:players,id'],
            'status' => ['required', 'string', 'in:confirmed,declined'],
            'team' => ['nullable', 'string', 'in:a,b'],
        ]);

        $player = $club->players()->findOrFail($validated['player_id']);

        $team = isset($validated['team']) ? AttendanceTeam::from($validated['team']) : null;

        try {
            $this->matchService->registerPlayer(
                $match,
                $player,
                AttendanceStatus::from($validated['status']),
                $team,
            );
        } catch (MatchFullException) {
            return back()->with('error', 'El cupo del partido está lleno.');
        }

        return back()->with('success', 'Registro actualizado.');
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
            return $this->updateStatus($match, $attendance, AttendanceStatus::from($validated['status']));
        }

        $data = array_filter([
            'team' => isset($validated['team']) ? AttendanceTeam::from($validated['team']) : null,
            'role' => isset($validated['role']) ? AttendanceRole::from($validated['role']) : null,
        ], fn ($value) => $value !== null);

        $attendance->update($data);

        return back()->with('success', 'Asistencia actualizada.');
    }

    private function updateStatus(FootballMatch $match, MatchAttendance $attendance, AttendanceStatus $status): RedirectResponse
    {
        if ($status === AttendanceStatus::Declined) {
            return $this->declineAttendance($match, $attendance);
        }

        return $this->confirmAttendance($match, $attendance, $status);
    }

    private function declineAttendance(FootballMatch $match, MatchAttendance $attendance): RedirectResponse
    {
        $shouldRecalculate = $attendance->role === AttendanceRole::Starter && $attendance->team !== null;

        $attendance->update([
            'status' => AttendanceStatus::Declined,
            'role' => AttendanceRole::Pending,
            'team' => null,
            'confirmed_at' => null,
        ]);

        if ($shouldRecalculate) {
            $this->matchService->recalculateRoles($match);
        }

        return back()->with('success', 'Asistencia actualizada.');
    }

    private function confirmAttendance(FootballMatch $match, MatchAttendance $attendance, AttendanceStatus $status): RedirectResponse
    {
        try {
            $role = $this->matchService->determineRole($match, $attendance->player_id, $attendance->team);
        } catch (MatchFullException) {
            return back()->with('error', 'El cupo del partido está lleno.');
        }

        $attendance->update([
            'status' => $status,
            'role' => $role,
            'confirmed_at' => now(),
        ]);

        if ($role === AttendanceRole::Substitute && $attendance->team) {
            $attendance->load('player');
            if ($attendance->player?->isGoalkeeper()) {
                $this->matchService->recalculateRoles($match);
            }
        }

        return back()->with('success', 'Asistencia actualizada.');
    }

    public function destroy(Club $club, FootballMatch $match, MatchAttendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $match);

        $wasStarter = $attendance->role === AttendanceRole::Starter;
        $hadTeam = $attendance->team !== null;

        $attendance->delete();

        if ($wasStarter && $hadTeam) {
            $this->matchService->recalculateRoles($match);
        }

        return back()->with('success', 'Jugador removido del partido.');
    }

    public function autoAssign(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        $this->matchService->autoAssignTeams($match);

        return back()->with('success', 'Equipos asignados automáticamente.');
    }
}
