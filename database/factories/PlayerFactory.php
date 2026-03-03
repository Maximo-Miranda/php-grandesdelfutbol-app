<?php

namespace Database\Factories;

use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'position' => fake()->randomElement(PlayerPosition::cases()),
            'jersey_number' => null,
            'goals' => 0,
            'assists' => 0,
            'matches_played' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'is_active' => true,
        ];
    }

    public function linked(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withStats(): static
    {
        return $this->state(fn (array $attributes) => [
            'goals' => fake()->numberBetween(0, 50),
            'assists' => fake()->numberBetween(0, 30),
            'matches_played' => fake()->numberBetween(1, 100),
            'yellow_cards' => fake()->numberBetween(0, 10),
            'red_cards' => fake()->numberBetween(0, 3),
        ]);
    }
}
