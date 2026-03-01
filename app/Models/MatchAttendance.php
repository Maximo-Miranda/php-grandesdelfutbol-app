<?php

namespace App\Models;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchAttendance extends Model
{
    /** @use HasFactory<\Database\Factories\MatchAttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_id',
        'status',
        'role',
        'team',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'role' => AttendanceRole::class,
            'team' => AttendanceTeam::class,
            'confirmed_at' => 'datetime',
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
