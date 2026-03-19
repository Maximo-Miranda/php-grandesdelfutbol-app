<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\ReelStatus;
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
 * @property ReelStatus $status
 * @property string $title
 * @property int $start_second
 * @property int $end_second
 * @property int $duration
 * @property string|null $error_message
 * @property \Carbon\CarbonImmutable|null $processed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\FootballMatch $match
 * @property-read \App\Models\MatchEvent|null $event
 * @property-read \App\Models\Player|null $player
 *
 * @method static \Database\Factories\MatchReelFactory factory($count = null, $state = [])
 */
class MatchReel extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\MatchReelFactory> */
    use HasFactory, HasPublicUlid, InteractsWithMedia;

    protected $fillable = [
        'match_id',
        'event_id',
        'player_id',
        'status',
        'title',
        'start_second',
        'end_second',
        'duration',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReelStatus::class,
            'start_second' => 'integer',
            'end_second' => 'integer',
            'duration' => 'integer',
            'processed_at' => 'immutable_datetime',
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
}
