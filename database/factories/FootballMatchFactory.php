<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FootballMatch>
 */
class FootballMatchFactory extends Factory
{
    protected $model = FootballMatch::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'club_id' => Club::factory(),
            'field_id' => null,
            'title' => fake()->words(3, true).' Match',
            'scheduled_at' => now()->addHours(fake()->numberBetween(1, 20)),
            'duration_minutes' => 60,
            'arrival_minutes' => 15,
            'max_players' => 10,
            'max_substitutes' => 4,
            'status' => MatchStatus::Upcoming,
            'share_token' => Str::random(16),
            'registration_opens_hours' => 24,
            'notes' => null,
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#1a1a1a',
            'team_b_color' => '#facc15',
            'is_friendly' => false,
            'is_recurring' => false,
            'recurrence_days' => 7,
            'auto_cancel' => false,
            'min_players_required' => 10,
            'registration_opens_at' => null,
            'cancel_hours_before' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MatchStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MatchStatus::Completed,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MatchStatus::Cancelled,
        ]);
    }

    public function scheduledPast(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => now()->subDay(),
        ]);
    }

    public function recurring(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurrence_days' => $days,
        ]);
    }

    public function withManualRegistration(CarbonImmutable|string $opensAt): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_opens_at' => $opensAt,
        ]);
    }

    public function withCancelHours(int $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'cancel_hours_before' => $hours,
        ]);
    }
}
