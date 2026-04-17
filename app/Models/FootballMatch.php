<?php

namespace App\Models;

use App\Concerns\BelongsToClub;
use App\Concerns\HasPublicUlid;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use Carbon\CarbonImmutable;
use Database\Factories\FootballMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $ulid
 * @property int $club_id
 * @property int|null $field_id
 * @property string $title
 * @property CarbonImmutable $scheduled_at
 * @property int $duration_minutes
 * @property int $arrival_minutes
 * @property int $max_players
 * @property int $max_substitutes
 * @property MatchStatus $status
 * @property string|null $share_token
 * @property int $registration_opens_hours
 * @property CarbonImmutable|null $registration_opens_at
 * @property string|null $notes
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $stats_finalized_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $team_a_name
 * @property string $team_b_name
 * @property string $team_a_color
 * @property string $team_b_color
 * @property int|null $team_a_score
 * @property int|null $team_b_score
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MatchAttendance> $attendances
 * @property-read int|null $attendances_count
 * @property-read Club $club
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MatchEvent> $events
 * @property-read int|null $events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MatchReel> $reels
 * @property-read int|null $reels_count
 * @property-read Field|null $field
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
 * @property bool $auto_started
 * @property array<array-key, mixed>|null $applied_stats
 * @property bool $is_recurring
 * @property int $recurrence_days
 * @property CarbonImmutable|null $next_match_created_at
 * @property bool $auto_cancel
 * @property int $min_players_required
 * @property int|null $cancel_hours_before
 * @property-read MatchVideoUpload|null $videoUpload
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereAppliedStats($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereAutoStarted($value)
 *
 * @mixin \Eloquent
 */
class FootballMatch extends Model
{
    use BelongsToClub, HasPublicUlid;

    /** @use HasFactory<FootballMatchFactory> */
    use HasFactory;

    public const int DEFAULT_CANCEL_HOURS_BEFORE = 10;

    /** @var string[] */
    public const array JERSEY_COLORS = [
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
        'auto_started',
        'share_token',
        'registration_opens_hours',
        'registration_opens_at',
        'notes',
        'started_at',
        'ended_at',
        'stats_finalized_at',
        'registration_notified_at',
        'applied_stats',
        'team_a_name',
        'team_b_name',
        'team_a_color',
        'team_b_color',
        'team_a_score',
        'team_b_score',
        'is_recurring',
        'recurrence_days',
        'next_match_created_at',
        'auto_cancel',
        'min_players_required',
        'cancel_hours_before',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'stats_finalized_at' => 'immutable_datetime',
            'registration_notified_at' => 'immutable_datetime',
            'applied_stats' => 'array',
            'auto_started' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_days' => 'integer',
            'next_match_created_at' => 'immutable_datetime',
            'auto_cancel' => 'boolean',
            'min_players_required' => 'integer',
            'duration_minutes' => 'integer',
            'arrival_minutes' => 'integer',
            'max_players' => 'integer',
            'max_substitutes' => 'integer',
            'registration_opens_hours' => 'integer',
            'registration_opens_at' => 'immutable_datetime',
            'cancel_hours_before' => 'integer',
        ];
    }

    public function effectiveRegistrationOpensAt(): CarbonImmutable
    {
        return $this->registration_opens_at
            ?? $this->scheduled_at->subHours($this->registration_opens_hours);
    }

    public function effectiveCancelHoursBefore(): int
    {
        return $this->cancel_hours_before ?? self::DEFAULT_CANCEL_HOURS_BEFORE;
    }

    public function teamName(AttendanceTeam $team): string
    {
        return match ($team) {
            AttendanceTeam::A => $this->team_a_name,
            AttendanceTeam::B => $this->team_b_name,
        };
    }

    protected static function booted(): void
    {
        static::updating(function (FootballMatch $match) {
            if ($match->isDirty('scheduled_at') && ! $match->auto_started) {
                $endsAt = $match->scheduled_at->addMinutes($match->duration_minutes);

                if ($match->scheduled_at->isFuture()) {
                    $match->status = MatchStatus::Upcoming;
                    $match->started_at = null;
                    $match->ended_at = null;
                    $match->next_match_created_at = null;
                } elseif ($endsAt->isFuture()) {
                    $match->status = MatchStatus::InProgress;
                    $match->started_at = $match->scheduled_at;
                    $match->ended_at = null;
                    $match->next_match_created_at = null;
                }
            }

            if ($match->registration_notified_at === null) {
                return;
            }

            if (! $match->isDirty(['scheduled_at', 'registration_opens_at', 'registration_opens_hours'])) {
                return;
            }

            if (now()->lt($match->effectiveRegistrationOpensAt())) {
                $match->registration_notified_at = null;
            }
        });
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

    public function reels(): HasMany
    {
        return $this->hasMany(MatchReel::class, 'match_id');
    }

    public function videoUpload(): HasOne
    {
        return $this->hasOne(MatchVideoUpload::class, 'football_match_id');
    }

    public function activeVideoServiceRequest(): HasOne
    {
        return $this->hasOne(VideoServiceRequest::class, 'match_id')->latestOfMany();
    }

    public function confirmedAttendeeUsers(): Collection
    {
        return $this->attendances()
            ->where('status', AttendanceStatus::Confirmed)
            ->with('player.user')
            ->get()
            ->pluck('player.user')
            ->filter();
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
