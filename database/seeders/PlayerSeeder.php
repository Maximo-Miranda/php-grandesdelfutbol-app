<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Player;
use Illuminate\Database\Seeder;

class PlayerSeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::where('name', 'FC Grandes')->first();

        if (! $club) {
            return;
        }

        $positions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];

        // Link players to existing club members
        foreach ($club->members as $index => $member) {
            Player::factory()->create([
                'club_id' => $club->id,
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'position' => $positions[$index % count($positions)],
                'jersey_number' => $index + 1,
            ]);
        }

        // Add some unlinked players
        Player::factory(3)->create([
            'club_id' => $club->id,
        ]);
    }
}
