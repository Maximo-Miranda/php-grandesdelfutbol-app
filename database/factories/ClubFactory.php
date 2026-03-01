<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Club>
 */
class ClubFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
            'invite_token' => Str::random(32),
            'is_invite_active' => false,
            'requires_approval' => false,
        ];
    }

    public function withInviteActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_invite_active' => true,
        ]);
    }

    public function withApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => true,
        ]);
    }
}
