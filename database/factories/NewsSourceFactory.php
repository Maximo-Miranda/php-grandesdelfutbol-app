<?php

namespace Database\Factories;

use App\Enums\NewsSourceType;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsSource>
 */
class NewsSourceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Deportes';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'type' => NewsSourceType::Rss,
            'url' => fake()->url(),
            'language' => 'es',
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 10),
            'fetch_interval_minutes' => 30,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function scorebat(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NewsSourceType::ScorebatApi,
        ]);
    }
}
