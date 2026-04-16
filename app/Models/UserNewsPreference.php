<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserNewsPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property array<int, string>|null $competitions
 * @property array<int, string>|null $teams
 * @property array<int, string>|null $topics
 * @property string|null $free_text_input
 * @property array<string, mixed>|null $ai_extracted_entities
 * @property bool $onboarding_completed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
class UserNewsPreference extends Model
{
    /** @use HasFactory<UserNewsPreferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'competitions',
        'teams',
        'topics',
        'free_text_input',
        'ai_extracted_entities',
        'onboarding_completed',
    ];

    protected function casts(): array
    {
        return [
            'competitions' => 'array',
            'teams' => 'array',
            'topics' => 'array',
            'ai_extracted_entities' => 'array',
            'onboarding_completed' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPreferences(): bool
    {
        if (! empty($this->competitions) || ! empty($this->teams) || ! empty($this->topics)) {
            return true;
        }

        $aiExtracted = $this->ai_extracted_entities ?? [];

        return ! empty($aiExtracted['teams'])
            || ! empty($aiExtracted['competitions'])
            || ! empty($aiExtracted['topics']);
    }
}
