<?php

namespace Database\Factories;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClubMember>
 */
class ClubMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'role' => ClubMemberRole::Player,
            'status' => ClubMemberStatus::Approved,
            'approved_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ClubMemberRole::Owner,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ClubMemberRole::Admin,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClubMemberStatus::Pending,
            'approved_at' => null,
        ]);
    }
}
