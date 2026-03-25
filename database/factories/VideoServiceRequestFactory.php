<?php

namespace Database\Factories;

use App\Enums\VideoServiceRequestStatus;
use App\Models\VideoServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VideoServiceRequest>
 */
class VideoServiceRequestFactory extends Factory
{
    protected $model = VideoServiceRequest::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'club_name' => fake()->company(),
            'preferred_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'message' => fake()->sentence(),
            'selected_plan' => fake()->randomElement(['basic', 'premium', 'pro']),
            'status' => VideoServiceRequestStatus::Pending,
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoServiceRequestStatus::Contacted,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoServiceRequestStatus::Completed,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoServiceRequestStatus::Rejected,
        ]);
    }
}
