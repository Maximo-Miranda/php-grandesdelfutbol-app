<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClubInvitation>
 */
class ClubInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(32),
            'status' => InvitationStatus::Pending,
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Accepted,
        ]);
    }
}
