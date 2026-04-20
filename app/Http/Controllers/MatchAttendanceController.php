<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
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
        $status = AttendanceStatus::from($validated['status']);
        $requestedTeam = isset($validated['team']) ? AttendanceTeam::from($validated['team']) : null;

        $resolved = $this->resolveTeamOrError($match, $player, $status, $requestedTeam);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $attendance = $this->matchService->registerPlayer($match, $player, $status, $resolved);

        $message = $attendance->status === AttendanceStatus::Waitlisted
            ? 'Quedaste en lista de espera.'
            : 'Registro actualizado.';

        return back()->with('success', $message);
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

        // For team-restricted matches, the team is determined by roster membership — admin cannot override
        if (isset($data['team']) && $match->isTeamRestricted()) {
            $attendance->loadMissing('player');
            $resolvedTeam = $match->resolveTeamForPlayer($attendance->player);
            if ($resolvedTeam !== null && $resolvedTeam !== $data['team']) {
                return back()->with('error', "{$attendance->player->display_name} pertenece al {$match->teamName($resolvedTeam)}. No se puede asignar a otro equipo en partidos con equipos seleccionados.");
            }
        }

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
        $wasConfirmed = $attendance->status === AttendanceStatus::Confirmed;

        $attendance->update([
            'status' => AttendanceStatus::Declined,
            'role' => AttendanceRole::Pending,
            'team' => null,
            'confirmed_at' => null,
        ]);

        if ($wasConfirmed) {
            $attendance->loadMissing('player');
            $this->matchService->promoteFromWaitlistAndNotify($match, $attendance->player);
        }

        return back()->with('success', 'Asistencia actualizada.');
    }

    private function confirmAttendance(FootballMatch $match, MatchAttendance $attendance, AttendanceStatus $status): RedirectResponse
    {
        $player = Player::anyClub()->findOrFail($attendance->player_id);

        $resolved = $this->resolveTeamOrError($match, $player, $status, $attendance->team);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $result = $this->matchService->registerPlayer($match, $player, $status, $resolved);

        $message = $result->status === AttendanceStatus::Waitlisted
            ? 'Jugador movido a lista de espera.'
            : 'Asistencia actualizada.';

        return back()->with('success', $message);
    }

    /**
     * For team-restricted matches, override the requested team with the player's actual roster team.
     * Returns a RedirectResponse with an error if the player is not eligible.
     */
    private function resolveTeamOrError(FootballMatch $match, Player $player, AttendanceStatus $status, ?AttendanceTeam $requestedTeam): AttendanceTeam|RedirectResponse|null
    {
        if (! $match->isTeamRestricted() || $status !== AttendanceStatus::Confirmed) {
            return $requestedTeam;
        }

        $resolved = $match->resolveTeamForPlayer($player, $requestedTeam);
        if ($resolved === null) {
            return back()->with('error', "{$player->display_name} no está en ninguno de los equipos de este partido.");
        }

        if ($requestedTeam !== null && $resolved !== $requestedTeam) {
            $correctTeam = $match->teamName($resolved);

            return back()->with('error', "{$player->display_name} pertenece al equipo {$correctTeam} en esta temporada. Cambia su equipo desde la gestión de equipos antes de reasignarlo.");
        }

        return $resolved;
    }

    public function destroy(Club $club, FootballMatch $match, MatchAttendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $match);

        $wasConfirmed = $attendance->status === AttendanceStatus::Confirmed;
        $canceler = $wasConfirmed ? $attendance->loadMissing('player')->player : null;

        $attendance->delete();

        if ($wasConfirmed) {
            $this->matchService->promoteFromWaitlistAndNotify($match, $canceler);
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
