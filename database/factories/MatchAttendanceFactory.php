<?php

namespace Database\Factories;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\FootballMatch;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchAttendance>
 */
class MatchAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => FootballMatch::factory(),
            'player_id' => Player::factory(),
            'status' => AttendanceStatus::Confirmed,
            'role' => AttendanceRole::Pending,
            'team' => null,
            'confirmed_at' => now(),
        ];
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Declined,
            'confirmed_at' => null,
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AttendanceRole::Starter,
        ]);
    }

    public function teamA(): static
    {
        return $this->state(fn (array $attributes) => [
            'team' => AttendanceTeam::A,
        ]);
    }

    public function teamB(): static
    {
        return $this->state(fn (array $attributes) => [
            'team' => AttendanceTeam::B,
        ]);
    }
}
