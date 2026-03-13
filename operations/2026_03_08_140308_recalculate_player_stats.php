<?php

use App\Enums\MatchEventType;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Recalculate all player stats from scratch.
 *
 * Why: The old MatchStatService::finalizeStats() did not track foul, save,
 * or handball events. Matches finalized before this change have incomplete
 * applied_stats snapshots and players are missing accumulated fouls/saves/handballs.
 *
 * Strategy (all inside a single transaction):
 * 1. Reset all player stat columns to zero.
 * 2. Recalculate stats directly from match_events for all completed matches.
 * 3. Rebuild applied_stats snapshot on each match.
 *
 * Does NOT use MatchStatService to avoid nested transactions and keep
 * the entire operation atomic — if anything fails, everything rolls back.
 */
return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        DB::transaction(function () {
            // 1. Reset all player stats to zero
            Player::query()->update([
                'goals' => 0,
                'assists' => 0,
                'matches_played' => 0,
                'yellow_cards' => 0,
                'red_cards' => 0,
                'fouls' => 0,
                'saves' => 0,
                'handballs' => 0,
            ]);

            // 2. Get all completed matches in chronological order
            $matches = FootballMatch::query()
                ->where('status', 'completed')
                ->orderBy('scheduled_at')
                ->get();

            foreach ($matches as $match) {
                // Collect events for this match
                $events = MatchEvent::where('match_id', $match->id)->get();

                $playerStats = [];

                foreach ($events as $event) {
                    $playerId = $event->player_id;
                    if (! $playerId) {
                        continue;
                    }

                    if (! isset($playerStats[$playerId])) {
                        $playerStats[$playerId] = [
                            'goals' => 0,
                            'assists' => 0,
                            'yellow_cards' => 0,
                            'red_cards' => 0,
                            'fouls' => 0,
                            'saves' => 0,
                            'handballs' => 0,
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
                        MatchEventType::OwnGoal => $playerStats[$playerId]['goals']++,
                        default => null,
                    };
                }

                // Apply stats to players
                foreach ($playerStats as $playerId => $stats) {
                    $player = Player::find($playerId);
                    if (! $player) {
                        continue;
                    }

                    foreach ($stats as $stat => $count) {
                        if ($count > 0) {
                            $player->increment($stat, $count);
                        }
                    }
                }

                // Increment matches_played for confirmed attendees
                $confirmedPlayerIds = $match->attendances()
                    ->where('status', 'confirmed')
                    ->pluck('player_id')
                    ->toArray();

                if ($confirmedPlayerIds) {
                    Player::query()
                        ->whereIn('id', $confirmedPlayerIds)
                        ->increment('matches_played');
                }

                // Rebuild the applied_stats snapshot
                $match->update([
                    'stats_finalized_at' => now(),
                    'applied_stats' => [
                        'player_stats' => $playerStats,
                        'confirmed_player_ids' => $confirmedPlayerIds,
                    ],
                ]);
            }

            Log::info("[OneTimeOperation] Recalculated player stats for {$matches->count()} matches.");
        });
    }
};
