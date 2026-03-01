<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballMatch extends Model
{
    /** @use HasFactory<\Database\Factories\FootballMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'club_id',
        'field_id',
        'title',
        'scheduled_at',
        'duration_minutes',
        'arrival_minutes',
        'max_players',
        'max_substitutes',
        'status',
        'share_token',
        'registration_opens_hours',
        'notes',
        'started_at',
        'ended_at',
        'stats_finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'stats_finalized_at' => 'datetime',
            'duration_minutes' => 'integer',
            'arrival_minutes' => 'integer',
            'max_players' => 'integer',
            'max_substitutes' => 'integer',
            'registration_opens_hours' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MatchAttendance::class, 'match_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', MatchStatus::Upcoming);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MatchStatus::Completed);
    }
}
