<?php

namespace Database\Factories;

use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'club_id' => Club::factory(),
            'name' => 'Temporada #'.fake()->numberBetween(1, 20),
            'matches_count' => Season::DEFAULT_MATCHES_COUNT,
            'status' => SeasonStatus::Active,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SeasonStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
