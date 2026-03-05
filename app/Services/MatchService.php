<?php

namespace App\Services;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MatchService
{
    public function createMatch(Club $club, array $data): FootballMatch
    {
        $scheduledAt = Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'));
        $isPast = $scheduledAt->isPast();
        $durationMinutes = $data['duration_minutes'] ?? 60;

        return FootballMatch::query()->create([
            'club_id' => $club->id,
            'field_id' => $data['field_id'] ?? null,
            'title' => $data['title'],
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $durationMinutes,
            'arrival_minutes' => $data['arrival_minutes'] ?? 15,
            'max_players' => $data['max_players'] ?? 10,
            'max_substitutes' => $data['max_substitutes'] ?? 4,
            'status' => $isPast ? MatchStatus::Completed : MatchStatus::Upcoming,
            'started_at' => $isPast ? $scheduledAt : null,
            'ended_at' => $isPast ? $scheduledAt->copy()->addMinutes($durationMinutes) : null,
            'share_token' => Str::random(16),
            'registration_opens_hours' => $data['registration_opens_hours'] ?? 24,
            'notes' => $data['notes'] ?? null,
            'team_a_name' => $data['team_a_name'] ?? 'Equipo A',
            'team_b_name' => $data['team_b_name'] ?? 'Equipo B',
            'team_a_color' => $data['team_a_color'] ?? '#1a1a1a',
            'team_b_color' => $data['team_b_color'] ?? '#facc15',
        ]);
    }

    public function registerPlayer(
        FootballMatch $match,
        Player $player,
        AttendanceStatus $status,
        ?AttendanceTeam $team = null,
    ): MatchAttendance {
        $role = AttendanceRole::Pending;

        if ($status === AttendanceStatus::Confirmed) {
            $confirmedCount = $match->attendances()
                ->where('status', AttendanceStatus::Confirmed)
                ->where('player_id', '!=', $player->id)
                ->count();

            $totalSlots = $match->max_players + $match->max_substitutes;

            if ($confirmedCount >= $totalSlots) {
                throw new \App\Exceptions\MatchFullException;
            }

            $role = $confirmedCount < $match->max_players
                ? AttendanceRole::Starter
                : AttendanceRole::Substitute;
        }

        return MatchAttendance::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'player_id' => $player->id,
            ],
            [
                'status' => $status,
                'role' => $status === AttendanceStatus::Confirmed ? $role : AttendanceRole::Pending,
                'team' => $status === AttendanceStatus::Confirmed ? $team : null,
                'confirmed_at' => $status === AttendanceStatus::Confirmed ? now() : null,
            ],
        );
    }

    public function isRegistrationOpen(FootballMatch $match): bool
    {
        if ($match->status !== MatchStatus::Upcoming) {
            return false;
        }

        $opensAt = $match->scheduled_at->subHours($match->registration_opens_hours);

        return now()->gte($opensAt);
    }

    public function autoAssignTeams(FootballMatch $match): void
    {
        $confirmedAttendances = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->get();

        $maxPerTeam = intdiv($match->max_players, 2);

        // Count already-assigned players
        $teamACount = $confirmedAttendances->where('team', AttendanceTeam::A)->count();
        $teamBCount = $confirmedAttendances->where('team', AttendanceTeam::B)->count();

        // Only distribute unassigned players
        $unassigned = $confirmedAttendances->whereNull('team')->shuffle();

        foreach ($unassigned as $attendance) {
            if ($teamACount < $maxPerTeam) {
                $attendance->update([
                    'team' => AttendanceTeam::A,
                    'role' => AttendanceRole::Starter,
                ]);
                $teamACount++;
            } elseif ($teamBCount < $maxPerTeam) {
                $attendance->update([
                    'team' => AttendanceTeam::B,
                    'role' => AttendanceRole::Starter,
                ]);
                $teamBCount++;
            } else {
                $attendance->update([
                    'role' => AttendanceRole::Substitute,
                ]);
            }
        }

        // Ensure already-assigned players have Starter role if within capacity
        foreach ($confirmedAttendances->whereNotNull('team') as $attendance) {
            if ($attendance->role !== AttendanceRole::Starter) {
                $attendance->update(['role' => AttendanceRole::Starter]);
            }
        }
    }
}
