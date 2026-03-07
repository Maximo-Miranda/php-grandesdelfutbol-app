<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\MatchEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $match_id
 * @property int $player_id
 * @property MatchEventType $event_type
 * @property int $minute
 * @property string|null $notes
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\FootballMatch $match
 * @property-read \App\Models\Player $player
 *
 * @method static \Database\Factories\MatchEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereMinute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent wherePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MatchEvent extends Model
{
    /** @use HasFactory<\Database\Factories\MatchEventFactory> */
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'match_id',
        'player_id',
        'event_type',
        'minute',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => MatchEventType::class,
            'minute' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
