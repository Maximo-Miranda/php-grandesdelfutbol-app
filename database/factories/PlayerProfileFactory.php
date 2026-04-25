<?php

namespace Database\Factories;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerProfile>
 */
class PlayerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nickname' => fake()->firstName(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'date_of_birth' => fake()->date(),
            'nationality' => fake()->country(),
            'bio' => fake()->sentence(),
            'preferred_position' => fake()->randomElement(['GK', 'CB', 'CM', 'ST']),
            'is_public_profile' => true,
        ];
    }
}
