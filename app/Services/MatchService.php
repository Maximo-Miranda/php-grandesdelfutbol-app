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
use Illuminate\Support\Str;

class MatchService
{
    public function createMatch(Club $club, array $data): FootballMatch
    {
        return FootballMatch::query()->create([
            'club_id' => $club->id,
            'field_id' => $data['field_id'] ?? null,
            'title' => $data['title'],
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'arrival_minutes' => $data['arrival_minutes'] ?? 15,
            'max_players' => $data['max_players'] ?? 10,
            'max_substitutes' => $data['max_substitutes'] ?? 4,
            'status' => MatchStatus::Upcoming,
            'share_token' => Str::random(16),
            'registration_opens_hours' => $data['registration_opens_hours'] ?? 24,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function registerPlayer(FootballMatch $match, Player $player, AttendanceStatus $status): MatchAttendance
    {
        return MatchAttendance::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'player_id' => $player->id,
            ],
            [
                'status' => $status,
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
            ->inRandomOrder()
            ->get();

        $maxPerTeam = intdiv($match->max_players, 2);
        $teamACount = 0;
        $teamBCount = 0;

        foreach ($confirmedAttendances as $attendance) {
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
    }
}
