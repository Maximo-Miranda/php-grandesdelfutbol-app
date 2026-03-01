<?php

namespace Database\Seeders;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'test@example.com')->first();

        $club = Club::factory()->create([
            'name' => 'FC Grandes',
            'description' => 'The best amateur football club.',
            'owner_id' => $owner->id,
        ]);

        ClubMember::factory()->owner()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
        ]);

        // Add some regular members
        $members = User::factory(5)->create();
        foreach ($members as $member) {
            ClubMember::factory()->create([
                'club_id' => $club->id,
                'user_id' => $member->id,
                'role' => ClubMemberRole::Player,
                'status' => ClubMemberStatus::Approved,
            ]);
        }

        // Second club
        $club2 = Club::factory()->create([
            'name' => 'Sporting Club',
            'owner_id' => $owner->id,
        ]);

        ClubMember::factory()->owner()->create([
            'club_id' => $club2->id,
            'user_id' => $owner->id,
        ]);
    }
}
