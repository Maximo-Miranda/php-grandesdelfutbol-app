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

            $events = $match->events()->get();

            $playerStats = [];

            foreach ($events as $event) {
                $playerId = $event->player_id;

                if (! isset($playerStats[$playerId])) {
                    $playerStats[$playerId] = [
                        'goals' => 0,
                        'assists' => 0,
                        'yellow_cards' => 0,
                        'red_cards' => 0,
                    ];
                }

                match ($event->event_type) {
                    MatchEventType::Goal, MatchEventType::PenaltyScored => $playerStats[$playerId]['goals']++,
                    MatchEventType::Assist => $playerStats[$playerId]['assists']++,
                    MatchEventType::YellowCard => $playerStats[$playerId]['yellow_cards']++,
                    MatchEventType::RedCard => $playerStats[$playerId]['red_cards']++,
                    MatchEventType::OwnGoal => $playerStats[$playerId]['goals']++,
                    default => null,
                };
            }

            foreach ($playerStats as $playerId => $stats) {
                $player = Player::find($playerId);
                if ($player) {
                    $player->increment('goals', $stats['goals']);
                    $player->increment('assists', $stats['assists']);
                    $player->increment('yellow_cards', $stats['yellow_cards']);
                    $player->increment('red_cards', $stats['red_cards']);
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
                $player->decrement('goals', $stats['goals']);
                $player->decrement('assists', $stats['assists']);
                $player->decrement('yellow_cards', $stats['yellow_cards']);
                $player->decrement('red_cards', $stats['red_cards']);
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
