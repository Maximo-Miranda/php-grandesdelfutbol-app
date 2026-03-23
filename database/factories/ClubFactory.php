<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
            'invite_token' => Str::random(32),
            'is_invite_active' => true,
            'requires_approval' => true,
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
