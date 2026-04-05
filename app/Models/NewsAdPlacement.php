<?php

namespace App\Models;

use App\Enums\NewsAdPlacementType;
use Carbon\CarbonImmutable;
use Database\Factories\NewsAdPlacementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $advertiser
 * @property string $image_url
 * @property string $target_url
 * @property NewsAdPlacementType $placement
 * @property int $frequency
 * @property int $priority
 * @property bool $is_active
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property int $impressions_count
 * @property int $clicks_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @mixin \Eloquent
 */
class NewsAdPlacement extends Model
{
    /** @use HasFactory<NewsAdPlacementFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'advertiser',
        'image_url',
        'target_url',
        'placement',
        'frequency',
        'priority',
        'is_active',
        'starts_at',
        'ends_at',
        'impressions_count',
        'clicks_count',
    ];

    protected function casts(): array
    {
        return [
            'placement' => NewsAdPlacementType::class,
            'frequency' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
        ];
    }
}
