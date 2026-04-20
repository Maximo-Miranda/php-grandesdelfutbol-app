<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'ulid' => (string) Str::ulid(),
            'club_id' => Club::factory(),
            'season_id' => Season::factory(),
            'name' => ucfirst($name),
            'normalized_name' => Team::normalize($name),
            'color' => fake()->hexColor(),
            'coach_player_id' => null,
            'captain_player_id' => null,
            'bio' => null,
        ];
    }

    public function forSeason(Season $season): static
    {
        return $this->state(fn () => [
            'club_id' => $season->club_id,
            'season_id' => $season->id,
        ]);
    }
}
