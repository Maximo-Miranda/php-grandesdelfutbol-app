<?php

namespace Database\Factories;

use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MatchVideoUpload>
 */
class MatchVideoUploadFactory extends Factory
{
    protected $model = MatchVideoUpload::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'football_match_id' => FootballMatch::factory(),
            'uploaded_by' => User::factory(),
            'bunny_video_id' => fake()->uuid(),
            'status' => VideoUploadStatus::Ready,
            'original_filename' => 'match-video.mp4',
            'original_size_bytes' => fake()->numberBetween(500_000_000, 15_000_000_000),
            'duration_seconds' => 3600,
            'video_offset_seconds' => 0,
            'uploaded_at' => now(),
            'encoded_at' => now(),
        ];
    }

    public function uploading(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoUploadStatus::Uploading,
            'duration_seconds' => null,
            'encoded_at' => null,
        ]);
    }

    public function encoding(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoUploadStatus::Encoding,
            'duration_seconds' => null,
            'encoded_at' => null,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoUploadStatus::Ready,
            'duration_seconds' => 3600,
            'encoded_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoUploadStatus::Failed,
            'duration_seconds' => null,
            'error_message' => 'Encoding failed: invalid video format.',
            'encoded_at' => null,
        ]);
    }
}
