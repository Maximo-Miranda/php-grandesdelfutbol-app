<?php

namespace Database\Factories;

use App\Enums\AttachmentCollection;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attachable_type' => Club::class,
            'attachable_id' => Club::factory(),
            'collection' => AttachmentCollection::Logo,
            'disk' => 'public',
            'path' => 'clubs/1/logo/'.fake()->uuid().'.png',
            'original_name' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(1024, 1048576),
        ];
    }

    public function logo(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => AttachmentCollection::Logo,
        ]);
    }

    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => AttachmentCollection::Photo,
        ]);
    }
}
