<?php

namespace App\Models;

use App\Enums\MatchEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    /** @use HasFactory<\Database\Factories\MatchEventFactory> */
    use HasFactory;

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
