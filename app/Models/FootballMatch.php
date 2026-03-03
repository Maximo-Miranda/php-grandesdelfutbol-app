<?php

namespace App\Models;

use App\Concerns\BelongsToClub;
use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $club_id
 * @property int|null $field_id
 * @property string $title
 * @property \Carbon\CarbonImmutable $scheduled_at
 * @property int $duration_minutes
 * @property int $arrival_minutes
 * @property int $max_players
 * @property int $max_substitutes
 * @property MatchStatus $status
 * @property string|null $share_token
 * @property int $registration_opens_hours
 * @property string|null $notes
 * @property \Carbon\CarbonImmutable|null $started_at
 * @property \Carbon\CarbonImmutable|null $ended_at
 * @property \Carbon\CarbonImmutable|null $stats_finalized_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string $team_a_name
 * @property string $team_b_name
 * @property string $team_a_color
 * @property string $team_b_color
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchAttendance> $attendances
 * @property-read int|null $attendances_count
 * @property-read \App\Models\Club $club
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\Field|null $field
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch completed()
 * @method static \Database\Factories\FootballMatchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch upcoming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereArrivalMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereEndedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereMaxPlayers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereMaxSubstitutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereRegistrationOpensHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereShareToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereStatsFinalizedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereTeamAColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereTeamAName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereTeamBColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereTeamBName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class FootballMatch extends Model
{
    use BelongsToClub;

    /** @use HasFactory<\Database\Factories\FootballMatchFactory> */
    use HasFactory;

    /** @var string[] */
    public const JERSEY_COLORS = [
        '#ffffff', '#1a1a1a', '#6b7280',
        '#dc2626', '#991b1b', '#ea580c',
        '#facc15', '#16a34a', '#065f46',
        '#06b6d4', '#2563eb', '#1e3a5f',
        '#7c3aed', '#db2777', '#92400e',
    ];

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
        'team_a_name',
        'team_b_name',
        'team_a_color',
        'team_b_color',
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
