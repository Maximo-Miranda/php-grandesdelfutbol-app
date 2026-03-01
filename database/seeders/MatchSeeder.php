<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\Field;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use Illuminate\Database\Seeder;

class MatchSeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::where('name', 'FC Grandes')->first();

        if (! $club) {
            return;
        }

        $field = Field::first();
        $players = $club->players;

        // Upcoming match
        $upcoming = FootballMatch::factory()->create([
            'club_id' => $club->id,
            'field_id' => $field?->id,
            'title' => 'Sunday Kickabout',
            'scheduled_at' => now()->addDays(3),
        ]);

        // Add some attendance
        foreach ($players->take(6) as $player) {
            MatchAttendance::factory()->create([
                'match_id' => $upcoming->id,
                'player_id' => $player->id,
                'status' => 'confirmed',
            ]);
        }

        // Another upcoming
        FootballMatch::factory()->create([
            'club_id' => $club->id,
            'title' => 'Wednesday Training Match',
            'scheduled_at' => now()->addDays(5),
        ]);

        // Completed match with events
        $completed = FootballMatch::factory()->completed()->create([
            'club_id' => $club->id,
            'field_id' => $field?->id,
            'title' => 'Last Week Match',
            'scheduled_at' => now()->subWeek(),
        ]);

        foreach ($players->take(8) as $player) {
            MatchAttendance::factory()->create([
                'match_id' => $completed->id,
                'player_id' => $player->id,
                'status' => 'confirmed',
            ]);
        }

        // Add some match events
        if ($players->count() >= 2) {
            MatchEvent::factory()->goal()->create([
                'match_id' => $completed->id,
                'player_id' => $players[0]->id,
                'minute' => 23,
            ]);

            MatchEvent::factory()->assist()->create([
                'match_id' => $completed->id,
                'player_id' => $players[1]->id,
                'minute' => 23,
            ]);

            MatchEvent::factory()->goal()->create([
                'match_id' => $completed->id,
                'player_id' => $players[1]->id,
                'minute' => 67,
            ]);

            MatchEvent::factory()->yellowCard()->create([
                'match_id' => $completed->id,
                'player_id' => $players[0]->id,
                'minute' => 45,
            ]);
        }
    }
}
