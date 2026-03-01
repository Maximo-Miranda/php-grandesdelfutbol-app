<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    protected $fillable = [
        'club_id',
        'user_id',
        'name',
        'position',
        'jersey_number',
        'goals',
        'assists',
        'matches_played',
        'yellow_cards',
        'red_cards',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'goals' => 'integer',
            'assists' => 'integer',
            'matches_played' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForClub($query, Club $club)
    {
        return $query->where('club_id', $club->id);
    }
}
