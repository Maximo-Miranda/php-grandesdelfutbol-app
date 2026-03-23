<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\AttendanceTeam;
use App\Enums\MatchEventType;
use Carbon\CarbonImmutable;
use Database\Factories\MatchEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $match_id
 * @property int|null $player_id
 * @property int|null $related_player_id
 * @property AttendanceTeam|null $team
 * @property MatchEventType $event_type
 * @property int $minute
 * @property int $second
 * @property string|null $notes
 * @property bool $highlighted
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read FootballMatch $match
 * @property-read Player|null $player
 * @property-read Player|null $relatedPlayer
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
    /** @use HasFactory<MatchEventFactory> */
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'match_id',
        'player_id',
        'related_player_id',
        'team',
        'event_type',
        'minute',
        'second',
        'notes',
        'highlighted',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => MatchEventType::class,
            'team' => AttendanceTeam::class,
            'minute' => 'integer',
            'second' => 'integer',
            'highlighted' => 'boolean',
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

    public function relatedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'related_player_id');
    }
}
