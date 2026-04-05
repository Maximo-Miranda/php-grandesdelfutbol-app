<?php

namespace Database\Factories;

use App\Enums\NewsAdPlacementType;
use App\Models\NewsAdPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsAdPlacement>
 */
class NewsAdPlacementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'advertiser' => fake()->company(),
            'image_url' => fake()->imageUrl(800, 200),
            'target_url' => fake()->url(),
            'placement' => NewsAdPlacementType::FeedCard,
            'frequency' => 5,
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
