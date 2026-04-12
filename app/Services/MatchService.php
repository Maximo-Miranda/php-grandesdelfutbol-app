<?php

namespace App\Services;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Exceptions\MatchFullException;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class MatchService
{
    private const int GOAL_WEIGHT = 40;

    private const int ASSIST_WEIGHT = 30;

    private const int EXPERIENCE_WEIGHT = 20;

    private const int DISCIPLINE_WEIGHT = 10;

    private const int EXPERIENCE_CAP_MATCHES = 40;

    public function createMatch(Club $club, array $data): FootballMatch
    {
        $scheduledAt = Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'));
        $isPast = $scheduledAt->isPast();
        $durationMinutes = $data['duration_minutes'] ?? 60;

        $match = FootballMatch::query()->create([
            'club_id' => $club->id,
            'field_id' => $data['field_id'] ?? null,
            'title' => $data['title'],
            'scheduled_at' => $scheduledAt,
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
            'is_recurring' => $data['is_recurring'] ?? true,
            'recurrence_days' => $data['recurrence_days'] ?? 7,
            'auto_cancel' => $data['auto_cancel'] ?? true,
            'min_players_required' => $data['min_players_required'] ?? ($data['max_players'] ?? 10),
        ]);

        if ($isPast) {
            $this->recreateIfRecurring($match);
        }

        return $match;
    }

    public function recreateIfRecurring(FootballMatch $match): ?FootballMatch
    {
        if (! $match->status->isFinished() || ! $match->is_recurring) {
            return null;
        }

        $claimed = FootballMatch::query()
            ->where('id', $match->id)
            ->whereNull('next_match_created_at')
            ->update(['next_match_created_at' => now()]);

        if ($claimed === 0) {
            return null;
        }

        $newScheduledAt = $match->scheduled_at->copy()->addDays($match->recurrence_days);

        while ($newScheduledAt->isPast()) {
            $newScheduledAt = $newScheduledAt->addDays($match->recurrence_days);
        }

        return FootballMatch::query()->create([
            'club_id' => $match->club_id,
            'field_id' => $match->field_id,
            'title' => $this->generateTitle($match, $newScheduledAt),
            'scheduled_at' => $newScheduledAt,
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => $match->max_players,
            'max_substitutes' => $match->max_substitutes,
            'status' => MatchStatus::Upcoming,
            'share_token' => Str::random(16),
            'registration_opens_hours' => $match->registration_opens_hours,
            'notes' => $match->notes,
            'team_a_name' => $match->team_a_name,
            'team_b_name' => $match->team_b_name,
            'team_a_color' => $match->team_a_color,
            'team_b_color' => $match->team_b_color,
            'is_recurring' => true,
            'recurrence_days' => $match->recurrence_days,
            'auto_cancel' => $match->auto_cancel,
            'min_players_required' => $match->min_players_required,
        ]);
    }

    private function generateTitle(FootballMatch $match, CarbonImmutable $scheduledAt): string
    {
        $parts = ['Partido'];

        $field = $match->field;
        if ($field) {
            $parts[] = $field->field_type->value;
        }

        $localDate = $scheduledAt->locale('es');
        $dayLabel = mb_ucfirst(str_replace('.', '', $localDate->isoFormat('ddd')));
        $monthLabel = mb_ucfirst(str_replace('.', '', $localDate->isoFormat('MMM')));

        $parts[] = $dayLabel.' '.$scheduledAt->day.' '.$monthLabel;

        return implode(' ', $parts);
    }

    public function registerPlayer(
        FootballMatch $match,
        Player $player,
        AttendanceStatus $status,
        ?AttendanceTeam $team = null,
    ): MatchAttendance {
        $role = AttendanceRole::Pending;

        if ($status === AttendanceStatus::Confirmed) {
            $role = $this->determineRole($match, $player->id, $team);
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

    /**
     * Determine whether a player should be starter or substitute based on per-team counts.
     */
    public function determineRole(FootballMatch $match, int $excludePlayerId, ?AttendanceTeam $team): AttendanceRole
    {
        $query = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->where('player_id', '!=', $excludePlayerId);

        if ($team) {
            $query->where('team', $team);
            $maxStarters = intdiv($match->max_players, 2);
            $maxTotal = $maxStarters + intdiv($match->max_substitutes, 2);
        } else {
            $maxStarters = $match->max_players;
            $maxTotal = $match->max_players + $match->max_substitutes;
        }

        $starterCount = (clone $query)->where('role', AttendanceRole::Starter)->count();
        $totalCount = $query->count();

        if ($match->status !== MatchStatus::Completed && $totalCount >= $maxTotal) {
            throw new MatchFullException;
        }

        return $starterCount < $maxStarters
            ? AttendanceRole::Starter
            : AttendanceRole::Substitute;
    }

    /**
     * Recalculate roles for all confirmed attendances per team, ordered by confirmed_at.
     */
    public function recalculateRoles(FootballMatch $match): void
    {
        $maxPerTeam = intdiv($match->max_players, 2);

        $attendances = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->whereNotNull('team')
            ->orderBy('confirmed_at')
            ->get();

        $starterIds = [];
        $subIds = [];
        $teamCounts = [AttendanceTeam::A->value => 0, AttendanceTeam::B->value => 0];

        foreach ($attendances as $attendance) {
            $teamKey = $attendance->team->value;

            if ($teamCounts[$teamKey] < $maxPerTeam) {
                $starterIds[] = $attendance->id;
                $teamCounts[$teamKey]++;
            } else {
                $subIds[] = $attendance->id;
            }
        }

        if ($starterIds) {
            MatchAttendance::whereIn('id', $starterIds)->update(['role' => AttendanceRole::Starter]);
        }

        if ($subIds) {
            MatchAttendance::whereIn('id', $subIds)->update(['role' => AttendanceRole::Substitute]);
        }
    }

    public function autoAssignTeams(FootballMatch $match): void
    {
        $confirmedAttendances = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->with('player')
            ->get();

        $maxPerTeam = intdiv($match->max_players, 2);

        // Separate pre-assigned and unassigned players
        $preAssigned = $confirmedAttendances->whereNotNull('team');
        $unassigned = $confirmedAttendances->whereNull('team');

        // Reset only unassigned players' roles
        foreach ($unassigned as $attendance) {
            $attendance->update(['role' => AttendanceRole::Pending]);
        }

        // Reset previously auto-assigned players (those with a team but reassignable)
        // When all players already have teams, reset all for a fresh sort
        if ($unassigned->isEmpty() && $preAssigned->isNotEmpty()) {
            foreach ($confirmedAttendances as $attendance) {
                $attendance->update(['team' => null, 'role' => AttendanceRole::Pending]);
            }
            $preAssigned = collect();
            $unassigned = $confirmedAttendances;
        }

        // Initialize teams with pre-assigned players
        $teams = [
            AttendanceTeam::A->value => ['score' => 0.0, 'positions' => [], 'count' => 0],
            AttendanceTeam::B->value => ['score' => 0.0, 'positions' => [], 'count' => 0],
        ];

        foreach ($preAssigned as $attendance) {
            $score = $this->calculatePlayerScore($attendance->player);
            $posGroup = $this->positionGroup($attendance->player?->position);
            $t = &$teams[$attendance->team->value];
            $t['score'] += $score;
            $t['positions'][$posGroup] = ($t['positions'][$posGroup] ?? 0) + 1;
            $t['count']++;
            $attendance->update(['role' => AttendanceRole::Starter]);
        }

        // Score and sort unassigned players by skill rating (highest first)
        $scored = $unassigned->map(fn (MatchAttendance $att) => [
            'attendance' => $att,
            'score' => $this->calculatePlayerScore($att->player),
            'position_group' => $this->positionGroup($att->player?->position),
        ])->sortByDesc('score')->values();

        $starterSlots = ($maxPerTeam * 2) - $preAssigned->count();
        $starters = $scored->take($starterSlots);
        $subs = $scored->slice($starterSlots);

        // Cap per team to ensure even distribution when fewer players than max
        $totalStarters = $preAssigned->count() + $starters->count();
        $effectiveMaxPerTeam = (int) ceil($totalStarters / 2);

        // Balanced distribution: snake draft sorted by score
        foreach ($starters as $item) {
            $posGroup = $item['position_group'];
            $a = &$teams[AttendanceTeam::A->value];
            $b = &$teams[AttendanceTeam::B->value];

            $preferA = $a['score'] < $b['score']
                || ($a['score'] === $b['score'] && ($a['positions'][$posGroup] ?? 0) <= ($b['positions'][$posGroup] ?? 0));

            $team = ($preferA && $a['count'] < $effectiveMaxPerTeam) || $b['count'] >= $effectiveMaxPerTeam
                ? AttendanceTeam::A
                : AttendanceTeam::B;

            $t = &$teams[$team->value];
            $t['score'] += $item['score'];
            $t['positions'][$posGroup] = ($t['positions'][$posGroup] ?? 0) + 1;
            $t['count']++;

            $item['attendance']->update(['team' => $team, 'role' => AttendanceRole::Starter]);
        }

        foreach ($subs as $item) {
            $item['attendance']->update(['role' => AttendanceRole::Substitute]);
        }
    }

    /**
     * Calculate a player's skill score based on stats.
     * Players without stats get a random baseline score.
     */
    private function calculatePlayerScore(?Player $player): float
    {
        if (! $player || $player->matches_played === 0) {
            return round(mt_rand(30, 50) + mt_rand(0, 99) / 100, 2);
        }

        $goalsPerMatch = $player->goals / $player->matches_played;
        $assistsPerMatch = $player->assists / $player->matches_played;

        $goalScore = $goalsPerMatch * self::GOAL_WEIGHT;
        $assistScore = $assistsPerMatch * self::ASSIST_WEIGHT;
        $experienceScore = min($player->matches_played / self::EXPERIENCE_CAP_MATCHES, 1) * self::EXPERIENCE_WEIGHT;
        $disciplineScore = max(0, self::DISCIPLINE_WEIGHT - ($player->yellow_cards * 0.5 + $player->red_cards * 2));

        // Small random factor to avoid identical sorts every time
        $randomFactor = mt_rand(0, 500) / 100;

        return round($goalScore + $assistScore + $experienceScore + $randomFactor + $disciplineScore, 2);
    }

    /**
     * Group positions into categories for balanced distribution.
     *
     * @return string defense|midfield|attack|goalkeeper
     */
    private function positionGroup(?PlayerPosition $position): string
    {
        if (! $position) {
            return 'midfield';
        }

        return match ($position) {
            PlayerPosition::Gk => 'goalkeeper',
            PlayerPosition::Cb, PlayerPosition::Lb, PlayerPosition::Rb => 'defense',
            PlayerPosition::Cdm, PlayerPosition::Cm, PlayerPosition::Cam => 'midfield',
            PlayerPosition::Lw, PlayerPosition::Rw, PlayerPosition::St, PlayerPosition::Cf => 'attack',
        };
    }
}
