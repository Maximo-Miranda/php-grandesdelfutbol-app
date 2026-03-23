<?php

namespace Database\Factories;

use App\Enums\MatchEventType;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MatchEvent>
 */
class MatchEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'match_id' => FootballMatch::factory(),
            'player_id' => Player::factory(),
            'event_type' => fake()->randomElement([MatchEventType::Goal, MatchEventType::Assist, MatchEventType::YellowCard, MatchEventType::RedCard]),
            'minute' => fake()->numberBetween(1, 90),
            'second' => fake()->numberBetween(0, 59),
            'notes' => null,
        ];
    }

    public function goal(): static
    {
        return $this->state(['event_type' => MatchEventType::Goal]);
    }

    public function assist(): static
    {
        return $this->state(['event_type' => MatchEventType::Assist]);
    }

    public function yellowCard(): static
    {
        return $this->state(['event_type' => MatchEventType::YellowCard]);
    }

    public function redCard(): static
    {
        return $this->state(['event_type' => MatchEventType::RedCard]);
    }

    public function foul(): static
    {
        return $this->state(['event_type' => MatchEventType::Foul]);
    }

    public function save(): static
    {
        return $this->state(['event_type' => MatchEventType::Save]);
    }

    public function handball(): static
    {
        return $this->state(['event_type' => MatchEventType::Handball]);
    }

    public function ownGoal(): static
    {
        return $this->state(['event_type' => MatchEventType::OwnGoal]);
    }

    public function penaltyScored(): static
    {
        return $this->state(['event_type' => MatchEventType::PenaltyScored]);
    }

    public function penaltyMissed(): static
    {
        return $this->state(['event_type' => MatchEventType::PenaltyMissed]);
    }

    public function teamEvent(MatchEventType $eventType = MatchEventType::ShotOnTarget, string $team = 'a'): static
    {
        return $this->state([
            'event_type' => $eventType,
            'team' => $team,
            'player_id' => null,
        ]);
    }

    public function neutralEvent(MatchEventType $eventType = MatchEventType::Timeout): static
    {
        return $this->state([
            'event_type' => $eventType,
            'team' => null,
            'player_id' => null,
        ]);
    }
}
