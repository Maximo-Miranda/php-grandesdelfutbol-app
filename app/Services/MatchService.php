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
use Illuminate\Support\Collection;
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

        $teamAId = $data['team_a_id'] ?? null;
        $singleTeam = (bool) ($data['single_team'] ?? false);
        $teamBId = $singleTeam ? null : ($data['team_b_id'] ?? null);

        $match = FootballMatch::query()->create([
            'club_id' => $club->id,
            'field_id' => $data['field_id'] ?? null,
            'team_a_id' => $teamAId,
            'team_b_id' => $teamBId,
            'is_friendly' => (bool) ($data['is_friendly'] ?? false),
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
            'registration_closes_at' => $data['registration_closes_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'team_a_name' => $teamAId ? null : ($data['team_a_name'] ?? 'Equipo A'),
            'team_b_name' => $singleTeam ? null : ($teamBId ? null : ($data['team_b_name'] ?? 'Equipo B')),
            'team_a_color' => $teamAId ? null : ($data['team_a_color'] ?? '#1a1a1a'),
            'team_b_color' => $singleTeam ? null : ($teamBId ? null : ($data['team_b_color'] ?? '#facc15')),
            'is_recurring' => $data['is_recurring'] ?? true,
            'recurrence_days' => $data['recurrence_days'] ?? 7,
            'auto_cancel' => $data['auto_cancel'] ?? true,
            'allow_outsiders' => (bool) ($data['allow_outsiders'] ?? false),
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

        $newRegistrationClosesAt = null;
        if ($match->registration_closes_at !== null) {
            $offsetSeconds = $match->registration_closes_at->diffInSeconds($match->scheduled_at);
            $newRegistrationClosesAt = $newScheduledAt->subSeconds($offsetSeconds);
        }

        $activeSeason = app(SeasonService::class)->activeFor($match->club);
        $carryTeamA = $match->team_a_id && $match->teamA?->season_id === $activeSeason->id ? $match->team_a_id : null;
        $carryTeamB = $match->team_b_id && $match->teamB?->season_id === $activeSeason->id ? $match->team_b_id : null;

        return FootballMatch::query()->create([
            'club_id' => $match->club_id,
            'field_id' => $match->field_id,
            'team_a_id' => $carryTeamA,
            'team_b_id' => $carryTeamB,
            'is_friendly' => $match->is_friendly,
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
            'registration_closes_at' => $newRegistrationClosesAt,
            'notes' => $match->notes,
            'team_a_name' => $carryTeamA ? null : $match->team_a_name,
            'team_b_name' => $carryTeamB ? null : $match->team_b_name,
            'team_a_color' => $carryTeamA ? null : $match->team_a_color,
            'team_b_color' => $carryTeamB ? null : $match->team_b_color,
            'is_recurring' => true,
            'recurrence_days' => $match->recurrence_days,
            'auto_cancel' => $match->auto_cancel,
            'allow_outsiders' => $match->allow_outsiders,
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

        $now = now();

        return $now->gte($match->effectiveRegistrationOpensAt())
            && $now->lt($match->effectiveRegistrationClosesAt());
    }

    public function isRegistrationClosed(FootballMatch $match): bool
    {
        if ($match->status !== MatchStatus::Upcoming) {
            return false;
        }

        return now()->gte($match->effectiveRegistrationClosesAt());
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
     * Re-credit events to the correct team based on player roster membership.
     * Used when a match transitions from free-text teams to team-restricted teams,
     * because pre-existing events may have an arbitrary team label that doesn't
     * match the player's actual roster team.
     *
     * Returns the number of events whose team was changed.
     */
    public function realignEventTeamsToRosters(FootballMatch $match): int
    {
        if (! $match->isTeamRestricted()) {
            return 0;
        }

        $changed = 0;
        $events = $match->events()->whereNotNull('player_id')->with('player')->get();

        foreach ($events as $event) {
            if (! $event->player) {
                continue;
            }

            $resolvedTeam = $match->resolveTeamForPlayer($event->player);
            if ($resolvedTeam !== null && $event->team !== $resolvedTeam) {
                $event->update(['team' => $resolvedTeam]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Sort attendances so that the first goalkeeper gets priority placement,
     * then fall back to confirmed_at ASC for the rest.
     *
     * Used by both rebalanceCapacity (decides Confirmed vs Waitlisted) and
     * recalculateRoles (decides Starter vs Substitute) to keep a single source
     * of truth for the "GK priority" rule.
     *
     * @param  iterable<MatchAttendance>  $attendances
     * @return Collection<int, MatchAttendance>
     */
    private function sortWithGoalkeeperPriority(iterable $attendances): Collection
    {
        $gkSlotUsed = false;

        return collect($attendances)->sortBy(function (MatchAttendance $att) use (&$gkSlotUsed) {
            $isGk = $att->player?->isGoalkeeper();
            if ($isGk && ! $gkSlotUsed) {
                $gkSlotUsed = true;

                return [0, $att->confirmed_at?->timestamp ?? PHP_INT_MAX];
            }

            return [1, $att->confirmed_at?->timestamp ?? PHP_INT_MAX];
        })->values();
    }

    /**
     * Rebalance team capacity after match config changes (max_players, max_substitutes, field_id).
     * Re-classifies all attendances per team into Starter / Substitute / Waitlisted based on
     * the new caps and the order they registered (oldest priority).
     *
     * Goalkeeper priority: the first GK on each team is guaranteed a confirmed slot,
     * even if they registered later than others — never sent to waitlist if space allows.
     *
     * Confirmed players exceeding capacity get demoted to Waitlist.
     * Waitlisted players get promoted if there's now room.
     */
    public function rebalanceCapacity(FootballMatch $match): void
    {
        if ($match->status->isFinished()) {
            return;
        }

        $maxStartersPerTeam = intdiv($match->max_players, 2);
        $maxSubsPerTeam = intdiv($match->max_substitutes, 2);
        $maxConfirmedPerTeam = $maxStartersPerTeam + $maxSubsPerTeam;

        $relevantStatuses = [AttendanceStatus::Confirmed, AttendanceStatus::Waitlisted];

        $attendances = $match->attendances()
            ->whereIn('status', $relevantStatuses)
            ->whereNotNull('team')
            ->with('player')
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($attendances, $maxConfirmedPerTeam) {
            foreach ([AttendanceTeam::A, AttendanceTeam::B] as $teamEnum) {
                $sorted = $this->sortWithGoalkeeperPriority($attendances->where('team', $teamEnum));

                foreach ($sorted as $index => $attendance) {
                    if ($index < $maxConfirmedPerTeam) {
                        $attendance->update([
                            'status' => AttendanceStatus::Confirmed,
                            'role' => AttendanceRole::Starter,
                            'confirmed_at' => $attendance->confirmed_at ?? now(),
                        ]);
                    } else {
                        $attendance->update([
                            'status' => AttendanceStatus::Waitlisted,
                            'role' => AttendanceRole::Pending,
                        ]);
                    }
                }
            }
        });

        // After confirm/waitlist split, run starter/substitute classification with GK priority
        $this->recalculateRoles($match);
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
            $sorted = $this->sortWithGoalkeeperPriority($attendances->where('team', $teamEnum));

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

    public const float AUTO_BALANCE_DELTA_PCT = 0.30;

    public function autoAssignTeams(FootballMatch $match): void
    {
        if ($match->status !== MatchStatus::Upcoming) {
            // Past or in-progress matches already have events with their own team
            // captured. Re-assigning teams now would desynchronize stats.
            return;
        }

        $confirmedAttendances = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->with('player')
            ->get();

        // For team-restricted matches: rostered players always go to their nómina team.
        // When the match opts into "allow_outsiders", non-rostered confirmees are drafted
        // between A and B honoring last-played preference + skill balance.
        if ($match->isTeamRestricted()) {
            $this->distributeRestrictedMatch($match, $confirmedAttendances);
            $this->recalculateRoles($match);

            return;
        }

        // Reset all confirmed for a fresh sort. Confirmation order is the source
        // of truth for who starts; team distribution within each pool honors the
        // player's last-played team preference and balances by skill afterward.
        foreach ($confirmedAttendances as $attendance) {
            $attendance->update(['team' => null, 'role' => AttendanceRole::Pending]);
        }

        $orderedByConfirmation = $confirmedAttendances
            ->sortBy(fn (MatchAttendance $a) => $a->confirmed_at?->timestamp ?? PHP_INT_MAX)
            ->values();

        $starterPool = $orderedByConfirmation->take($match->max_players);
        $substitutePool = $orderedByConfirmation->slice($match->max_players);

        $preferences = $this->resolveTeamPreferences($confirmedAttendances, $match);

        $this->draftPoolIntoTeams($starterPool, AttendanceRole::Starter, $preferences);
        $this->draftPoolIntoTeams($substitutePool, AttendanceRole::Substitute, $preferences);
    }

    /**
     * Team-restricted match assignment: rostered players go to their nómina team,
     * outsiders (only allowed when allow_outsiders=true) are drafted between the
     * two teams honoring preference + skill balance, respecting per-team capacity.
     *
     * @param  Collection<int, MatchAttendance>  $confirmedAttendances
     */
    private function distributeRestrictedMatch(FootballMatch $match, Collection $confirmedAttendances): void
    {
        $rostered = collect();
        $outsiders = collect();

        foreach ($confirmedAttendances as $attendance) {
            $rosterTeam = $match->resolveTeamForPlayer($attendance->player);

            if ($rosterTeam !== null) {
                $attendance->update([
                    'team' => $rosterTeam,
                    'role' => AttendanceRole::Starter,
                ]);
                $rostered->push(['attendance' => $attendance, 'team' => $rosterTeam]);

                continue;
            }

            // Outsider — clear any previous team assignment so the draft can place them
            $attendance->update(['team' => null, 'role' => AttendanceRole::Pending]);
            $outsiders->push($attendance);
        }

        if ($outsiders->isEmpty()) {
            return;
        }

        $maxPerTeam = (int) ceil($match->max_players / 2);
        $rosteredCounts = [
            AttendanceTeam::A->value => $rostered->where('team', AttendanceTeam::A)->count(),
            AttendanceTeam::B->value => $rostered->where('team', AttendanceTeam::B)->count(),
        ];

        $preferences = $this->resolveTeamPreferences($outsiders, $match);

        $this->draftOutsidersIntoTeams($outsiders, $rosteredCounts, $maxPerTeam, $preferences);
    }

    /**
     * Draft outsiders between the two teams of a restricted match. Each team
     * starts with an existing rostered count; outsiders fill remaining slots
     * honoring preference, then balancing by skill score.
     *
     * @param  Collection<int, MatchAttendance>  $outsiders
     * @param  array<string, int>  $rosteredCounts  team value => current count
     * @param  array<int, AttendanceTeam>  $preferences
     */
    private function draftOutsidersIntoTeams(
        Collection $outsiders,
        array $rosteredCounts,
        int $maxPerTeam,
        array $preferences,
    ): void {
        $teamStats = [
            AttendanceTeam::A->value => [
                'score' => 0.0,
                'positions' => [],
                'count' => $rosteredCounts[AttendanceTeam::A->value],
            ],
            AttendanceTeam::B->value => [
                'score' => 0.0,
                'positions' => [],
                'count' => $rosteredCounts[AttendanceTeam::B->value],
            ],
        ];

        $scored = $outsiders->map(fn (MatchAttendance $att) => [
            'attendance' => $att,
            'score' => $this->calculatePlayerScore($att->player),
            'position_group' => $this->positionGroup($att->player?->position),
            'preference' => $preferences[$att->player_id] ?? null,
        ])->sortByDesc('score')->values();

        $unplaced = collect();

        foreach ($scored as $item) {
            $pref = $item['preference'];
            if ($pref !== null && $teamStats[$pref->value]['count'] < $maxPerTeam) {
                $this->addToTeamStats($teamStats[$pref->value], null, $item['score'], $item['position_group']);
                $item['attendance']->update(['team' => $pref, 'role' => AttendanceRole::Starter]);

                continue;
            }
            $unplaced->push($item);
        }

        foreach ($unplaced as $item) {
            // Allow drafting beyond maxPerTeam if both teams are already over (no slots left
            // anywhere) — caller still benefits from balancing as fallback.
            $effectiveMax = max(
                $maxPerTeam,
                $teamStats[AttendanceTeam::A->value]['count'] + 1,
                $teamStats[AttendanceTeam::B->value]['count'] + 1,
            );
            $team = $this->pickTeamForDraft($teamStats, $item['position_group'], $effectiveMax);
            $this->addToTeamStats($teamStats[$team->value], null, $item['score'], $item['position_group']);
            $item['attendance']->update(['team' => $team, 'role' => AttendanceRole::Starter]);
        }
    }

    /**
     * Distribute a pool of attendances across teams A/B honoring last-played
     * team preference when possible, then snake-draft balancing by skill score
     * and position group. A final rebalance pass swaps a pair of players if
     * the resulting score delta exceeds AUTO_BALANCE_DELTA_PCT.
     *
     * @param  Collection<int, MatchAttendance>  $pool
     * @param  array<int, AttendanceTeam>  $preferences  player_id => preferred team
     */
    private function draftPoolIntoTeams(Collection $pool, AttendanceRole $role, array $preferences = []): void
    {
        if ($pool->isEmpty()) {
            return;
        }

        $teamStats = [
            AttendanceTeam::A->value => ['score' => 0.0, 'positions' => [], 'count' => 0],
            AttendanceTeam::B->value => ['score' => 0.0, 'positions' => [], 'count' => 0],
        ];

        $scored = $pool->map(fn (MatchAttendance $att) => [
            'attendance' => $att,
            'score' => $this->calculatePlayerScore($att->player),
            'position_group' => $this->positionGroup($att->player?->position),
            'preference' => $preferences[$att->player_id] ?? null,
        ])->sortByDesc('score')->values();

        $maxPerTeam = (int) ceil($scored->count() / 2);
        $unplaced = collect();

        // Phase 1: place players who have a known team preference, top-skill first,
        // honoring preference unless the team is already at capacity.
        foreach ($scored as $item) {
            $pref = $item['preference'];
            if ($pref !== null && $teamStats[$pref->value]['count'] < $maxPerTeam) {
                $this->addToTeamStats($teamStats[$pref->value], null, $item['score'], $item['position_group']);
                $item['attendance']->update(['team' => $pref, 'role' => $role]);

                continue;
            }
            $unplaced->push($item);
        }

        // Phase 2: snake-draft remaining players (no preference, or preferred team full)
        foreach ($unplaced as $item) {
            $team = $this->pickTeamForDraft($teamStats, $item['position_group'], $maxPerTeam);
            $this->addToTeamStats($teamStats[$team->value], null, $item['score'], $item['position_group']);
            $item['attendance']->update(['team' => $team, 'role' => $role]);
        }

        // Phase 3: rebalance if final delta is too large by swapping one pair
        $this->rebalancePoolIfNeeded($scored, $teamStats);
    }

    /**
     * If the score delta between teams exceeds AUTO_BALANCE_DELTA_PCT, find a
     * single player swap that reduces it the most. Runs at most twice to converge.
     *
     * @param  Collection<int, array{attendance: MatchAttendance, score: float, position_group: string, preference: ?AttendanceTeam}>  $scored
     * @param  array<string, array{score: float, positions: array<string, int>, count: int}>  $teamStats
     */
    private function rebalancePoolIfNeeded(Collection $scored, array &$teamStats): void
    {
        for ($iteration = 0; $iteration < 2; $iteration++) {
            $a = $teamStats[AttendanceTeam::A->value]['score'];
            $b = $teamStats[AttendanceTeam::B->value]['score'];
            $max = max($a, $b);
            $delta = $max > 0 ? abs($a - $b) / $max : 0.0;

            if ($delta <= self::AUTO_BALANCE_DELTA_PCT) {
                return;
            }

            $heavier = $a > $b ? AttendanceTeam::A : AttendanceTeam::B;
            $lighter = $heavier->opposite();

            $heavyPlayers = $scored->filter(fn (array $i) => $i['attendance']->team === $heavier);
            $lightPlayers = $scored->filter(fn (array $i) => $i['attendance']->team === $lighter);

            $bestSwap = null;
            $bestDelta = abs($a - $b);

            foreach ($heavyPlayers as $heavyItem) {
                foreach ($lightPlayers as $lightItem) {
                    $newA = $heavier === AttendanceTeam::A
                        ? $a - $heavyItem['score'] + $lightItem['score']
                        : $a - $lightItem['score'] + $heavyItem['score'];
                    $newB = ($a + $b) - $newA;
                    $newDelta = abs($newA - $newB);

                    if ($newDelta < $bestDelta) {
                        $bestDelta = $newDelta;
                        $bestSwap = ['heavy' => $heavyItem, 'light' => $lightItem];
                    }
                }
            }

            if ($bestSwap === null) {
                return;
            }

            $bestSwap['heavy']['attendance']->update(['team' => $lighter]);
            $bestSwap['light']['attendance']->update(['team' => $heavier]);

            $heavyKey = $heavier->value;
            $lightKey = $lighter->value;
            $teamStats[$heavyKey]['score'] = $teamStats[$heavyKey]['score'] - $bestSwap['heavy']['score'] + $bestSwap['light']['score'];
            $teamStats[$lightKey]['score'] = $teamStats[$lightKey]['score'] - $bestSwap['light']['score'] + $bestSwap['heavy']['score'];
        }
    }

    /**
     * Build a map of player_id => preferred team based on the most recent
     * completed match in the same club where the player had a team assigned.
     *
     * @param  Collection<int, MatchAttendance>  $attendances
     * @return array<int, AttendanceTeam>
     */
    private function resolveTeamPreferences(Collection $attendances, FootballMatch $match): array
    {
        $playerIds = $attendances->pluck('player_id')->unique()->values()->all();

        if ($playerIds === []) {
            return [];
        }

        $rows = DB::table('match_attendances as ma')
            ->join('matches as m', 'm.id', '=', 'ma.match_id')
            ->select('ma.player_id', 'ma.team')
            ->whereIn('ma.player_id', $playerIds)
            ->where('m.club_id', $match->club_id)
            ->where('m.id', '!=', $match->id)
            ->where('m.status', MatchStatus::Completed->value)
            ->where('ma.status', AttendanceStatus::Confirmed->value)
            ->whereNotNull('ma.team')
            ->orderBy('ma.player_id')
            ->orderByDesc('m.scheduled_at')
            ->get();

        $preferences = [];
        foreach ($rows as $row) {
            if (! isset($preferences[$row->player_id])) {
                $preferences[(int) $row->player_id] = AttendanceTeam::from($row->team);
            }
        }

        return $preferences;
    }

    /**
     * Calculate a player's skill score based on stats.
     * Players without stats get a neutral fixed baseline so teams of statless
     * players stay balanced and honored preferences are not rebalanced away.
     * Includes a small random factor to avoid identical sorts every time.
     */
    private function calculatePlayerScore(?Player $player): float
    {
        if (! $player || $player->matches_played === 0) {
            // Players without recorded stats get a neutral, near-equal baseline so
            // a team of them stays balanced and the skill rebalance never overrides
            // their honored team preference. The sub-point factor only breaks sort
            // ties; it is far too small to push the team delta past
            // AUTO_BALANCE_DELTA_PCT.
            return round(self::STATLESS_BASELINE_SCORE + mt_rand(0, 99) / 100, 2);
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
     * For team-restricted matches, never falls back — the player must stay
     * on their assigned team (based on roster membership).
     *
     * @return array{role: AttendanceRole, team: ?AttendanceTeam}|null
     */
    private function findPromotionSlot(FootballMatch $match, int $playerId, ?AttendanceTeam $preferredTeam): ?array
    {
        $slot = $this->tryAssignment($match, $playerId, $preferredTeam);

        if ($slot !== null || $preferredTeam === null) {
            return $slot;
        }

        // Team-restricted matches: do not fall back to opposite team — player belongs to one team only.
        if ($match->isTeamRestricted()) {
            return null;
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

    /**
     * Atomically swap the teams of two confirmed attendances.
     * Roles stay as-is; the swap UI restricts pairs to the same role so the
     * starter/substitute counts per team remain balanced by construction.
     */
    public function swapPlayerTeams(MatchAttendance $a, MatchAttendance $b): void
    {
        if ($a->match_id !== $b->match_id) {
            throw new \InvalidArgumentException('Las asistencias pertenecen a partidos distintos.');
        }

        if ($a->id === $b->id) {
            throw new \InvalidArgumentException('No se puede intercambiar una asistencia consigo misma.');
        }

        $a->loadMissing('match');
        if ($a->match?->status !== MatchStatus::Upcoming) {
            // Once the match is in-progress or completed, events have captured
            // the team per action — swapping retroactively would break stats.
            throw new \InvalidArgumentException('Solo se puede intercambiar antes del inicio del partido.');
        }

        if ($a->match->isTeamRestricted()) {
            // Roster-based modes lock each player to their season team. Swapping
            // would silently violate roster membership and skew team-level stats.
            throw new \InvalidArgumentException('Este partido tiene equipos con nómina — no se puede intercambiar entre ellos.');
        }

        if ($a->status !== AttendanceStatus::Confirmed || $b->status !== AttendanceStatus::Confirmed) {
            throw new \InvalidArgumentException('Solo se pueden intercambiar jugadores confirmados.');
        }

        if ($a->team === null || $b->team === null) {
            throw new \InvalidArgumentException('Ambos jugadores deben tener equipo asignado.');
        }

        if ($a->team === $b->team) {
            throw new \InvalidArgumentException('Los jugadores ya están en el mismo equipo.');
        }

        DB::transaction(function () use ($a, $b) {
            $teamA = $a->team;
            $teamB = $b->team;
            $a->update(['team' => $teamB]);
            $b->update(['team' => $teamA]);
        });
    }

    /**
     * Suggest swap candidates from the opposite team, sorted by affinity:
     * same position group first, then closest skill score to the source.
     *
     * @return Collection<int, array{attendance: MatchAttendance, score: float, recommended: bool, same_position_group: bool}>
     */
    public function recommendSwapCandidates(MatchAttendance $source): Collection
    {
        $source->loadMissing('player', 'match');

        $match = $source->match;

        if ($source->team === null) {
            return collect();
        }

        $sourceScore = $this->calculatePlayerScore($source->player);
        $sourceGroup = $this->positionGroup($source->player?->position);
        $oppositeTeam = $source->team->opposite();

        $candidates = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->where('team', $oppositeTeam)
            ->where('role', $source->role)
            ->where('id', '!=', $source->id)
            ->with('player.user.playerProfile')
            ->get();

        $scored = $candidates->map(function (MatchAttendance $candidate) use ($sourceScore, $sourceGroup) {
            $candidateScore = $this->calculatePlayerScore($candidate->player);
            $candidateGroup = $this->positionGroup($candidate->player?->position);
            $samePositionGroup = $candidateGroup === $sourceGroup;

            return [
                'attendance' => $candidate,
                'score' => $candidateScore,
                'same_position_group' => $samePositionGroup,
                'affinity' => ($samePositionGroup ? 0 : 100) + abs($sourceScore - $candidateScore),
            ];
        })->sortBy('affinity')->values();

        return $scored->map(fn (array $entry, int $index) => [
            'attendance' => $entry['attendance'],
            'score' => $entry['score'],
            'same_position_group' => $entry['same_position_group'],
            'recommended' => $index < 2,
        ])->values();
    }

    /**
     * Compute the balance between teams using starter scores only.
     * Returns delta_pct (0..1) and the outlier players on the heavier team
     * who contribute the most to the imbalance.
     *
     * @return array{
     *     team_a_score: float,
     *     team_b_score: float,
     *     delta_pct: float,
     *     heavier_team: ?string,
     *     outliers: array<int, array{attendance: MatchAttendance, score: float}>
     * }
     */
    public function teamBalanceReport(FootballMatch $match): array
    {
        $starters = $match->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->where('role', AttendanceRole::Starter)
            ->whereNotNull('team')
            ->with('player.user.playerProfile')
            ->get();

        $scoresByTeam = [
            AttendanceTeam::A->value => 0.0,
            AttendanceTeam::B->value => 0.0,
        ];
        $playersByTeam = [
            AttendanceTeam::A->value => [],
            AttendanceTeam::B->value => [],
        ];

        foreach ($starters as $attendance) {
            $score = $this->calculatePlayerScore($attendance->player);
            $teamKey = $attendance->team->value;
            $scoresByTeam[$teamKey] += $score;
            $playersByTeam[$teamKey][] = ['attendance' => $attendance, 'score' => $score];
        }

        $a = $scoresByTeam[AttendanceTeam::A->value];
        $b = $scoresByTeam[AttendanceTeam::B->value];
        $max = max($a, $b);
        $delta = $max > 0 ? abs($a - $b) / $max : 0.0;

        $heavierTeam = null;
        $outliers = [];
        if ($a > $b) {
            $heavierTeam = AttendanceTeam::A->value;
            $outliers = collect($playersByTeam[AttendanceTeam::A->value])
                ->sortByDesc('score')
                ->take(2)
                ->values()
                ->all();
        } elseif ($b > $a) {
            $heavierTeam = AttendanceTeam::B->value;
            $outliers = collect($playersByTeam[AttendanceTeam::B->value])
                ->sortByDesc('score')
                ->take(2)
                ->values()
                ->all();
        }

        return [
            'team_a_score' => round($a, 2),
            'team_b_score' => round($b, 2),
            'delta_pct' => round($delta, 4),
            'heavier_team' => $heavierTeam,
            'outliers' => $outliers,
        ];
    }
}
