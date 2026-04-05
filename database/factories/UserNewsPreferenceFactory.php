<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNewsPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNewsPreference>
 */
class UserNewsPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'competitions' => ['la_liga', 'champions_league'],
            'teams' => ['real_madrid'],
            'topics' => ['transfers'],
            'onboarding_completed' => true,
        ];
    }

    public function notOnboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_completed' => false,
            'competitions' => null,
            'teams' => null,
            'topics' => null,
        ]);
    }
}
