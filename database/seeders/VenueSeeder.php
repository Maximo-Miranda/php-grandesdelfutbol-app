<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Field;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::where('name', 'FC Grandes')->first();

        if (! $club) {
            return;
        }

        $venue = Venue::factory()->create([
            'club_id' => $club->id,
            'name' => 'Main Stadium',
            'address' => '123 Football Ave',
        ]);

        Field::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Field A',
            'field_type' => '7v7',
        ]);

        Field::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Field B',
            'field_type' => '5v5',
        ]);

        $venue2 = Venue::factory()->create([
            'club_id' => $club->id,
            'name' => 'Training Ground',
        ]);

        Field::factory()->create([
            'venue_id' => $venue2->id,
            'name' => 'Practice Field',
            'field_type' => '11v11',
        ]);
    }
}
