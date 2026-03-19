<?php

namespace Database\Factories;

use App\Enums\ReelStatus;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchReel>
 */
class MatchReelFactory extends Factory
{
    public function definition(): array
    {
        $startSecond = fake()->numberBetween(0, 5000);
        $duration = fake()->numberBetween(15, 30);

        return [
            'ulid' => (string) Str::ulid(),
            'match_id' => FootballMatch::factory(),
            'event_id' => null,
            'player_id' => null,
            'status' => ReelStatus::Pending,
            'title' => fake()->sentence(3),
            'start_second' => $startSecond,
            'end_second' => $startSecond + $duration,
            'duration' => $duration,
            'error_message' => null,
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ReelStatus::Completed,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ReelStatus::Failed,
            'error_message' => 'Download failed',
        ]);
    }

    public function processing(): static
    {
        return $this->state(['status' => ReelStatus::Processing]);
    }

    public function forEvent(MatchEvent $event): static
    {
        return $this->state([
            'event_id' => $event->id,
            'player_id' => $event->player_id,
            'match_id' => $event->match_id,
        ]);
    }
}
