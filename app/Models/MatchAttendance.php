<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $match_id
 * @property int $player_id
 * @property AttendanceStatus $status
 * @property AttendanceRole $role
 * @property AttendanceTeam|null $team
 * @property \Carbon\CarbonImmutable|null $confirmed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\FootballMatch $match
 * @property-read \App\Models\Player $player
 *
 * @method static \Database\Factories\MatchAttendanceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance wherePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereTeam($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MatchAttendance extends Model
{
    /** @use HasFactory<\Database\Factories\MatchAttendanceFactory> */
    use HasFactory, HasPublicUlid;

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
