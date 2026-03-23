<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\ReelSource;
use App\Enums\ReelStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MatchReelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property int $match_id
 * @property int|null $event_id
 * @property int|null $player_id
 * @property int|null $requested_by
 * @property ReelStatus $status
 * @property ReelSource $source
 * @property string $title
 * @property int $start_second
 * @property int $end_second
 * @property int $duration
 * @property string|null $error_message
 * @property string|null $request_notes
 * @property CarbonImmutable|null $processed_at
 * @property int $view_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read FootballMatch $match
 * @property-read MatchEvent|null $event
 * @property-read Player|null $player
 * @property-read User|null $requester
 *
 * @method static \Database\Factories\MatchReelFactory factory($count = null, $state = [])
 */
class MatchReel extends Model implements HasMedia
{
    /** @use HasFactory<MatchReelFactory> */
    use HasFactory, HasPublicUlid, InteractsWithMedia;

    protected $appends = ['media_url'];

    protected $fillable = [
        'match_id',
        'event_id',
        'player_id',
        'requested_by',
        'status',
        'source',
        'title',
        'start_second',
        'end_second',
        'duration',
        'error_message',
        'request_notes',
        'processed_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReelStatus::class,
            'source' => ReelSource::class,
            'start_second' => 'integer',
            'end_second' => 'integer',
            'duration' => 'integer',
            'processed_at' => 'immutable_datetime',
            'view_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reel')
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime']);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(MatchEvent::class, 'event_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function getMediaUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('reel');

        if (! $media) {
            return null;
        }

        return $media->getTemporaryUrl(now()->addHour());
    }
}
