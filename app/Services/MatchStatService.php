<?php

namespace App\Services;

use App\Enums\MatchEventType;
use App\Models\FootballMatch;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class MatchStatService
{
    public function finalizeStats(FootballMatch $match): void
    {
        DB::transaction(function () use ($match) {
            if ($match->stats_finalized_at) {
                $this->revertStats($match);
            }

            $events = $match->events()->whereNotNull('player_id')->get();

            $playerStats = [];

            foreach ($events as $event) {
                $playerId = $event->player_id;

                if (! isset($playerStats[$playerId])) {
                    $playerStats[$playerId] = [
                        'goals' => 0,
                        'assists' => 0,
                        'yellow_cards' => 0,
                        'red_cards' => 0,
                        'fouls' => 0,
                        'saves' => 0,
                        'handballs' => 0,
                        'own_goals' => 0,
                        'penalties_scored' => 0,
                        'penalties_missed' => 0,
                    ];
                }

                match ($event->event_type) {
                    MatchEventType::Goal, MatchEventType::PenaltyScored => $playerStats[$playerId]['goals']++,
                    MatchEventType::Assist => $playerStats[$playerId]['assists']++,
                    MatchEventType::YellowCard => $playerStats[$playerId]['yellow_cards']++,
                    MatchEventType::RedCard => $playerStats[$playerId]['red_cards']++,
                    MatchEventType::Foul => $playerStats[$playerId]['fouls']++,
                    MatchEventType::Save => $playerStats[$playerId]['saves']++,
                    MatchEventType::Handball => $playerStats[$playerId]['handballs']++,
                    MatchEventType::OwnGoal => $playerStats[$playerId]['own_goals']++,
                    MatchEventType::PenaltyMissed => $playerStats[$playerId]['penalties_missed']++,
                    default => null,
                };

                // PenaltyScored also counts as a penalty stat (separate from goals)
                if ($event->event_type === MatchEventType::PenaltyScored) {
                    $playerStats[$playerId]['penalties_scored']++;
                }
            }

            foreach ($playerStats as $playerId => $stats) {
                $player = Player::find($playerId);
                if ($player) {
                    foreach ($stats as $stat => $count) {
                        if ($count > 0) {
                            $player->increment($stat, $count);
                        }
                    }
                }
            }

            $confirmedPlayerIds = $match->attendances()
                ->where('status', 'confirmed')
                ->pluck('player_id')
                ->toArray();

            Player::query()
                ->whereIn('id', $confirmedPlayerIds)
                ->increment('matches_played');

            $match->update([
                'stats_finalized_at' => now(),
                'applied_stats' => [
                    'player_stats' => $playerStats,
                    'confirmed_player_ids' => $confirmedPlayerIds,
                ],
            ]);
        });
    }

    /**
     * @return array{a: array<string, int>, b: array<string, int>}
     */
    public function getTeamStats(FootballMatch $match): array
    {
        $stats = ['a' => [], 'b' => []];
        $teamEvents = $match->events()->whereNotNull('team')->get();

        foreach ($teamEvents as $event) {
            $team = $event->team->value;
            $type = $event->event_type->value;
            $stats[$team][$type] = ($stats[$team][$type] ?? 0) + 1;
        }

        return $stats;
    }

    public function recalculateScore(FootballMatch $match): void
    {
        $scoringTypes = [MatchEventType::Goal, MatchEventType::PenaltyScored, MatchEventType::OwnGoal];

        $events = $match->events()->whereIn('event_type', $scoringTypes)->get();
        $teamByPlayer = $match->attendances()
            ->whereNotNull('team')
            ->pluck('team', 'player_id');

        $teamAScore = 0;
        $teamBScore = 0;

        foreach ($events as $event) {
            $playerTeam = $event->team?->value
                ?? $teamByPlayer->get($event->player_id)?->value;

            if ($event->event_type === MatchEventType::OwnGoal) {
                $playerTeam === 'a' ? $teamBScore++ : $teamAScore++;
            } else {
                $playerTeam === 'a' ? $teamAScore++ : $teamBScore++;
            }
        }

        $match->update([
            'team_a_score' => $teamAScore,
            'team_b_score' => $teamBScore,
        ]);
    }

    public function revertStats(FootballMatch $match): void
    {
        $appliedStats = $match->applied_stats;

        if (! $appliedStats) {
            return;
        }

        $playerStats = $appliedStats['player_stats'] ?? [];
        $confirmedPlayerIds = $appliedStats['confirmed_player_ids'] ?? [];

        foreach ($playerStats as $playerId => $stats) {
            $player = Player::find($playerId);
            if ($player) {
                foreach ($stats as $stat => $count) {
                    $value = (int) $count;
                    if ($value > 0) {
                        $player->decrement($stat, $value);
                    }
                }
            }
        }

        Player::query()
            ->whereIn('id', $confirmedPlayerIds)
            ->decrement('matches_played');

        $match->update([
            'stats_finalized_at' => null,
            'applied_stats' => null,
        ]);
    }
}
