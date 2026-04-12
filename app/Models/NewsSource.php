<?php

namespace App\Models;

use App\Enums\NewsSourceType;
use Carbon\CarbonImmutable;
use Database\Factories\NewsSourceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property NewsSourceType $type
 * @property string $url
 * @property string $language
 * @property string|null $logo_url
 * @property bool $is_active
 * @property int $priority
 * @property int $fetch_interval_minutes
 * @property CarbonImmutable|null $last_fetched_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, NewsArticle> $articles
 * @property-read int|null $articles_count
 *
 * @mixin \Eloquent
 */
class NewsSource extends Model
{
    /** @use HasFactory<NewsSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'url',
        'language',
        'logo_url',
        'is_active',
        'priority',
        'fetch_interval_minutes',
        'last_fetched_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsSourceType::class,
            'is_active' => 'boolean',
            'priority' => 'integer',
            'fetch_interval_minutes' => 'integer',
            'last_fetched_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<NewsArticle, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class);
    }

    public function needsFetching(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_fetched_at === null) {
            return true;
        }

        return $this->last_fetched_at->addMinutes($this->fetch_interval_minutes)->isPast();
    }
}
