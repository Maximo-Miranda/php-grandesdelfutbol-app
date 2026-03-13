<?php

namespace Database\Factories;

use App\Models\FootballMatch;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchEvent>
 */
class MatchEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'match_id' => FootballMatch::factory(),
            'player_id' => Player::factory(),
            'event_type' => fake()->randomElement(['goal', 'assist', 'yellow_card', 'red_card']),
            'minute' => fake()->numberBetween(1, 90),
            'notes' => null,
        ];
    }

    public function goal(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'goal',
        ]);
    }

    public function assist(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'assist',
        ]);
    }

    public function yellowCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'yellow_card',
        ]);
    }

    public function redCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'red_card',
        ]);
    }

    public function foul(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'foul',
        ]);
    }

    public function save(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'save',
        ]);
    }

    public function handball(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'handball',
        ]);
    }

    public function ownGoal(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'own_goal',
        ]);
    }

    public function penaltyScored(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'penalty_scored',
        ]);
    }

    public function penaltyMissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'penalty_missed',
        ]);
    }
}
