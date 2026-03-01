<?php

namespace App\Services;

use App\Enums\MatchEventType;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\DB;

class MatchStatService
{
    public function finalizeStats(FootballMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $events = $match->events()->with('player')->get();

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

            // Update each player's stats
            foreach ($playerStats as $playerId => $stats) {
                $player = \App\Models\Player::find($playerId);
                if ($player) {
                    $player->increment('goals', $stats['goals']);
                    $player->increment('assists', $stats['assists']);
                    $player->increment('yellow_cards', $stats['yellow_cards']);
                    $player->increment('red_cards', $stats['red_cards']);
                }
            }

            // Increment matches_played for all confirmed attendees
            $confirmedPlayerIds = $match->attendances()
                ->where('status', 'confirmed')
                ->pluck('player_id');

            \App\Models\Player::query()
                ->whereIn('id', $confirmedPlayerIds)
                ->increment('matches_played');

            $match->update(['stats_finalized_at' => now()]);
        });
    }
}
