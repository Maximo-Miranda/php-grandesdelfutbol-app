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
use App\Models\User;
use App\Notifications\WaitlistDemotedByGoalkeeperNotification;
use App\Notifications\WaitlistPromotedNotification;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
        $durationMinutes = $data['duration_minutes'] ?? 60;
        $endsAt = $scheduledAt->copy()->addMinutes($durationMinutes);

        if ($scheduledAt->isFuture()) {
            $status = MatchStatus::Upcoming;
            $startedAt = null;
            $endedAt = null;
        } elseif ($endsAt->isFuture()) {
            $status = MatchStatus::InProgress;
            $startedAt = $scheduledAt;
            $endedAt = null;
        } else {
            $status = MatchStatus::Completed;
            $startedAt = $scheduledAt;
            $endedAt = $endsAt;
        }

        $match = FootballMatch::query()->create([
            'club_id' => $club->id,
            'field_id' => $data['field_id'] ?? null,
            'title' => $data['title'],
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'arrival_minutes' => $data['arrival_minutes'] ?? 15,
            'max_players' => $data['max_players'] ?? 10,
            'max_substitutes' => $data['max_substitutes'] ?? 4,
            'status' => $status,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'share_token' => Str::random(16),
            'registration_opens_hours' => $data['registration_opens_hours'] ?? 24,
            'registration_opens_at' => $data['registration_opens_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'team_a_name' => $data['team_a_name'] ?? 'Equipo A',
            'team_b_name' => $data['team_b_name'] ?? 'Equipo B',
            'team_a_color' => $data['team_a_color'] ?? '#1a1a1a',
            'team_b_color' => $data['team_b_color'] ?? '#facc15',
            'is_recurring' => $data['is_recurring'] ?? true,
            'recurrence_days' => $data['recurrence_days'] ?? 7,
            'auto_cancel' => $data['auto_cancel'] ?? true,
            'min_players_required' => $data['min_players_required'] ?? ($data['max_players'] ?? 10),
            'cancel_hours_before' => $data['cancel_hours_before'] ?? null,
        ]);

        if ($status === MatchStatus::Completed) {
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

        $newScheduledAt = $match->scheduled_at->addDays($match->recurrence_days);

        while ($newScheduledAt->isPast()) {
            $newScheduledAt = $newScheduledAt->addDays($match->recurrence_days);
        }

        $newRegistrationOpensAt = null;
        if ($match->registration_opens_at !== null) {
            $offsetSeconds = $match->registration_opens_at->diffInSeconds($match->scheduled_at);
            $newRegistrationOpensAt = $newScheduledAt->subSeconds($offsetSeconds);
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
            'registration_opens_at' => $newRegistrationOpensAt,
            'notes' => $match->notes,
            'team_a_name' => $match->team_a_name,
            'team_b_name' => $match->team_b_name,
            'team_a_color' => $match->team_a_color,
            'team_b_color' => $match->team_b_color,
            'is_recurring' => true,
            'recurrence_days' => $match->recurrence_days,
            'auto_cancel' => $match->auto_cancel,
            'min_players_required' => $match->min_players_required,
            'cancel_hours_before' => $match->cancel_hours_before,
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
        $isConfirming = $status === AttendanceStatus::Confirmed;

        if ($isConfirming) {
            try {
                $role = $this->determineRole($match, $player->id, $team);
            } catch (MatchFullException) {
                return $this->handleFullMatchRegistration($match, $player, $team);
            }
        } else {
            $role = AttendanceRole::Pending;
        }

        $existing = $match->attendances()
            ->where('player_id', $player->id)
            ->first();

        $wasConfirmed = $existing?->status === AttendanceStatus::Confirmed;

        $attendance = $this->upsertAttendance($match, $player, [
            'status' => $status,
            'role' => $role,
            'team' => $isConfirming ? $team : null,
            'confirmed_at' => $isConfirming ? now() : null,
        ]);

        if (
            $isConfirming
            && $team !== null
            && $role === AttendanceRole::Substitute
            && $player->isGoalkeeper()
            && ! $this->hasGoalkeeperStarter($match, $team, $player->id)
        ) {
            $this->promoteGoalkeeperByDemotingLastStarter($match, $attendance, $team);
            $attendance->refresh();
        }

        if ($status === AttendanceStatus::Declined && $wasConfirmed) {
            $this->promoteFromWaitlistAndNotify($match, $player);
        }

        return $attendance;
    }

    public function isRegistrationOpen(FootballMatch $match): bool
    {
        if ($match->status !== MatchStatus::Upcoming) {
            return false;
        }

        return now()->gte($match->effectiveRegistrationOpensAt());
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
     * Recalculate roles for all confirmed attendances per team.
     * Goalkeepers get priority: the first confirmed GK on each team is placed
     * ahead of outfield players, ensuring each team has a GK starter when possible.
     */
    public function recalculateRoles(FootballMatch $match): void
    {
        $maxPerTeam = intdiv($match->max_players, 2);

        $attendances = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->whereNotNull('team')
            ->with('player')
            ->orderBy('confirmed_at')
            ->get();

        $starterIds = [];
        $subIds = [];

        foreach ([AttendanceTeam::A, AttendanceTeam::B] as $teamEnum) {
            $teamAttendances = $attendances->where('team', $teamEnum);

            $gkSlotUsed = false;
            $sorted = $teamAttendances->sortBy(function ($att) use (&$gkSlotUsed) {
                $isGk = $att->player?->isGoalkeeper();
                if ($isGk && ! $gkSlotUsed) {
                    $gkSlotUsed = true;

                    return [0, $att->confirmed_at?->timestamp ?? PHP_INT_MAX];
                }

                return [1, $att->confirmed_at?->timestamp ?? PHP_INT_MAX];
            })->values();

            $count = 0;
            foreach ($sorted as $attendance) {
                if ($count < $maxPerTeam) {
                    $starterIds[] = $attendance->id;
                    $count++;
                } else {
                    $subIds[] = $attendance->id;
                }
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

        $preAssigned = $confirmedAttendances->whereNotNull('team');
        $unassigned = $confirmedAttendances->whereNull('team');

        // When all players already have teams, reset all for a fresh sort
        if ($unassigned->isEmpty() && $preAssigned->isNotEmpty()) {
            foreach ($confirmedAttendances as $attendance) {
                $attendance->update(['team' => null, 'role' => AttendanceRole::Pending]);
            }
            $preAssigned = collect();
            $unassigned = $confirmedAttendances;
        } else {
            foreach ($unassigned as $attendance) {
                $attendance->update(['role' => AttendanceRole::Pending]);
            }
        }

        $teamStats = [
            AttendanceTeam::A->value => ['score' => 0.0, 'positions' => [], 'count' => 0],
            AttendanceTeam::B->value => ['score' => 0.0, 'positions' => [], 'count' => 0],
        ];

        foreach ($preAssigned as $attendance) {
            $this->addToTeamStats($teamStats[$attendance->team->value], $attendance->player);
            $attendance->update(['role' => AttendanceRole::Starter]);
        }

        $scored = $unassigned->map(fn (MatchAttendance $att) => [
            'attendance' => $att,
            'score' => $this->calculatePlayerScore($att->player),
            'position_group' => $this->positionGroup($att->player?->position),
        ])->sortByDesc('score')->values();

        $starterSlots = ($maxPerTeam * 2) - $preAssigned->count();
        $starters = $scored->take($starterSlots);
        $subs = $scored->slice($starterSlots);

        $effectiveMaxPerTeam = (int) ceil(($preAssigned->count() + $starters->count()) / 2);

        foreach ($starters as $item) {
            $team = $this->pickTeamForDraft($teamStats, $item['position_group'], $effectiveMaxPerTeam);

            $this->addToTeamStats($teamStats[$team->value], null, $item['score'], $item['position_group']);
            $item['attendance']->update(['team' => $team, 'role' => AttendanceRole::Starter]);
        }

        foreach ($subs as $item) {
            $item['attendance']->update(['role' => AttendanceRole::Substitute]);
        }
    }

    /**
     * Calculate a player's skill score based on stats.
     * Players without stats get a random baseline score.
     * Includes a small random factor to avoid identical sorts every time.
     */
    private function calculatePlayerScore(?Player $player): float
    {
        if (! $player || $player->matches_played === 0) {
            return round(mt_rand(30, 50) + mt_rand(0, 99) / 100, 2);
        }

        $matches = $player->matches_played;

        return round(
            ($player->goals / $matches) * self::GOAL_WEIGHT
            + ($player->assists / $matches) * self::ASSIST_WEIGHT
            + min($matches / self::EXPERIENCE_CAP_MATCHES, 1) * self::EXPERIENCE_WEIGHT
            + max(0, self::DISCIPLINE_WEIGHT - ($player->yellow_cards * 0.5 + $player->red_cards * 2))
            + mt_rand(0, 500) / 100,
            2,
        );
    }

    private function hasGoalkeeperStarter(FootballMatch $match, AttendanceTeam $team, int $excludePlayerId = 0): bool
    {
        return $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->where('role', AttendanceRole::Starter)
            ->where('team', $team)
            ->where('player_id', '!=', $excludePlayerId)
            ->whereHas('player', fn ($q) => $q->where('position', PlayerPosition::Gk))
            ->exists();
    }

    private function promoteGoalkeeperByDemotingLastStarter(
        FootballMatch $match,
        MatchAttendance $gkAttendance,
        AttendanceTeam $team,
    ): void {
        $lastNonGkStarter = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->where('role', AttendanceRole::Starter)
            ->where('team', $team)
            ->where('id', '!=', $gkAttendance->id)
            ->whereHas('player', fn ($q) => $q->where('position', '!=', PlayerPosition::Gk)->orWhereNull('position'))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->first();

        if (! $lastNonGkStarter) {
            return;
        }

        $lastNonGkStarter->update(['role' => AttendanceRole::Substitute]);
        $gkAttendance->update(['role' => AttendanceRole::Starter]);
    }

    /**
     * Accumulate a player's score and position into the given team stats bucket.
     *
     * @param  array{score: float, positions: array<string, int>, count: int}  $stats
     */
    private function addToTeamStats(array &$stats, ?Player $player, ?float $score = null, ?string $positionGroup = null): void
    {
        $score ??= $this->calculatePlayerScore($player);
        $positionGroup ??= $this->positionGroup($player?->position);

        $stats['score'] += $score;
        $stats['positions'][$positionGroup] = ($stats['positions'][$positionGroup] ?? 0) + 1;
        $stats['count']++;
    }

    /**
     * Pick the best team for the next draft pick using snake-draft balancing.
     *
     * @param  array<string, array{score: float, positions: array<string, int>, count: int}>  $teamStats
     */
    private function pickTeamForDraft(array &$teamStats, string $positionGroup, int $effectiveMaxPerTeam): AttendanceTeam
    {
        $a = $teamStats[AttendanceTeam::A->value];
        $b = $teamStats[AttendanceTeam::B->value];

        $preferA = $a['score'] < $b['score']
            || ($a['score'] === $b['score'] && ($a['positions'][$positionGroup] ?? 0) <= ($b['positions'][$positionGroup] ?? 0));

        if (($preferA && $a['count'] < $effectiveMaxPerTeam) || $b['count'] >= $effectiveMaxPerTeam) {
            return AttendanceTeam::A;
        }

        return AttendanceTeam::B;
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

    /**
     * Promote the earliest waitlisted player and notify them + admins.
     */
    public function promoteFromWaitlistAndNotify(FootballMatch $match, ?Player $canceler = null): ?MatchAttendance
    {
        $result = $this->promoteFromWaitlistWithContext($match);

        $this->recalculateRoles($match);

        if ($result === null) {
            return null;
        }

        $promoted = $result['attendance'];
        $preferredTeam = $result['preferredTeam'];

        $promoted->loadMissing('player.user');

        Log::info('waitlist.promoted', [
            'match_id' => $match->id,
            'player_id' => $promoted->player_id,
            'role' => $promoted->role->value,
            'preferred_team' => $preferredTeam?->value,
            'assigned_team' => $promoted->team?->value,
            'canceler_player_id' => $canceler?->id,
        ]);

        $this->notifyPlayerAndAdmins(
            $match,
            $promoted->player?->user,
            new WaitlistPromotedNotification(
                $match,
                $promoted->player,
                $promoted->role,
                $canceler,
                $preferredTeam,
                $promoted->team,
            ),
        );

        return $promoted;
    }

    /**
     * Atomically promote the earliest waitlisted attendance to confirmed.
     */
    public function promoteFromWaitlist(FootballMatch $match): ?MatchAttendance
    {
        return $this->promoteFromWaitlistWithContext($match)['attendance'] ?? null;
    }

    /**
     * @return array{attendance: MatchAttendance, preferredTeam: ?AttendanceTeam}|null
     */
    private function promoteFromWaitlistWithContext(FootballMatch $match): ?array
    {
        if ($match->status->isFinished()) {
            return null;
        }

        $hasWaitlisted = $match->attendances()
            ->where('status', AttendanceStatus::Waitlisted)
            ->exists();

        if (! $hasWaitlisted) {
            return null;
        }

        return DB::transaction(function () use ($match) {
            $next = $match->attendances()
                ->where('status', AttendanceStatus::Waitlisted)
                ->orderBy('confirmed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            $preferredTeam = $next->team;
            $assignment = $this->findPromotionSlot($match, $next->player_id, $preferredTeam);

            if ($assignment === null) {
                return null;
            }

            $next->update([
                'status' => AttendanceStatus::Confirmed,
                'role' => $assignment['role'],
                'team' => $assignment['team'],
            ]);

            return ['attendance' => $next, 'preferredTeam' => $preferredTeam];
        });
    }

    /**
     * Find a slot for a waitlisted player, falling back to the other team
     * if their preferred team is still full.
     *
     * @return array{role: AttendanceRole, team: ?AttendanceTeam}|null
     */
    private function findPromotionSlot(FootballMatch $match, int $playerId, ?AttendanceTeam $preferredTeam): ?array
    {
        $slot = $this->tryAssignment($match, $playerId, $preferredTeam);

        if ($slot !== null || $preferredTeam === null) {
            return $slot;
        }

        return $this->tryAssignment($match, $playerId, $preferredTeam->opposite());
    }

    /** @return array{role: AttendanceRole, team: ?AttendanceTeam}|null */
    private function tryAssignment(FootballMatch $match, int $playerId, ?AttendanceTeam $team): ?array
    {
        try {
            return [
                'role' => $this->determineRole($match, $playerId, $team),
                'team' => $team,
            ];
        } catch (MatchFullException) {
            return null;
        }
    }

    private function handleFullMatchRegistration(
        FootballMatch $match,
        Player $player,
        ?AttendanceTeam $team,
    ): MatchAttendance {
        if (
            $team !== null
            && $player->isGoalkeeper()
            && ! $this->hasGoalkeeperStarter($match, $team, $player->id)
        ) {
            $cascadeResult = $this->applyGoalkeeperCascadeOnFullMatch($match, $player, $team);

            if ($cascadeResult !== null) {
                return $cascadeResult;
            }
        }

        return $this->upsertAttendance($match, $player, [
            'status' => AttendanceStatus::Waitlisted,
            'role' => AttendanceRole::Pending,
            'team' => $team,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * When a GK confirms to a full match and their team has no GK starter:
     * last non-GK starter → substitute, last non-GK substitute → waitlist, GK → starter.
     * Returns the GK attendance, or null if no cascade chain was available.
     */
    private function applyGoalkeeperCascadeOnFullMatch(
        FootballMatch $match,
        Player $goalkeeper,
        AttendanceTeam $team,
    ): ?MatchAttendance {
        return DB::transaction(function () use ($match, $goalkeeper, $team) {
            $lastStarter = $this->findLastNonGoalkeeper($match, $team, AttendanceRole::Starter);
            $lastSubstitute = $this->findLastNonGoalkeeper($match, $team, AttendanceRole::Substitute);

            if (! $lastStarter || ! $lastSubstitute) {
                return null;
            }

            $demotedPlayer = $lastSubstitute->player;

            $lastSubstitute->update([
                'status' => AttendanceStatus::Waitlisted,
                'role' => AttendanceRole::Pending,
            ]);

            $lastStarter->update(['role' => AttendanceRole::Substitute]);

            $gkAttendance = $this->upsertAttendance($match, $goalkeeper, [
                'status' => AttendanceStatus::Confirmed,
                'role' => AttendanceRole::Starter,
                'team' => $team,
                'confirmed_at' => now(),
            ]);

            Log::info('waitlist.gk_cascade', [
                'match_id' => $match->id,
                'goalkeeper_player_id' => $goalkeeper->id,
                'team' => $team->value,
                'demoted_starter_id' => $lastStarter->player_id,
                'demoted_to_waitlist_id' => $lastSubstitute->player_id,
            ]);

            if ($demotedPlayer) {
                $this->notifyPlayerAndAdmins(
                    $match,
                    $demotedPlayer->user,
                    new WaitlistDemotedByGoalkeeperNotification($match, $demotedPlayer, $goalkeeper, $team),
                );
            }

            return $gkAttendance;
        });
    }

    /**
     * Mark all waitlisted attendances of a match as declined.
     * Call on match cancellation to avoid zombie waitlist entries.
     */
    public function clearWaitlistedAttendances(FootballMatch $match): int
    {
        return $match->attendances()
            ->where('status', AttendanceStatus::Waitlisted)
            ->update([
                'status' => AttendanceStatus::Declined,
                'role' => AttendanceRole::Pending,
                'team' => null,
                'confirmed_at' => null,
            ]);
    }

    private function notifyPlayerAndAdmins(FootballMatch $match, ?User $playerUser, LaravelNotification $notification): void
    {
        $recipients = collect([$playerUser])
            ->filter()
            ->merge($match->club->adminUsers())
            ->unique('id');

        Notification::send($recipients, $notification);
    }

    private function findLastNonGoalkeeper(
        FootballMatch $match,
        AttendanceTeam $team,
        AttendanceRole $role,
    ): ?MatchAttendance {
        return $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->where('role', $role)
            ->where('team', $team)
            ->whereHas('player', fn ($q) => $q
                ->where('position', '!=', PlayerPosition::Gk)
                ->orWhereNull('position'))
            ->with('player.user')
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->first();
    }

    private function upsertAttendance(FootballMatch $match, Player $player, array $data): MatchAttendance
    {
        return $match->attendances()->updateOrCreate(
            ['player_id' => $player->id],
            $data,
        );
    }
}
