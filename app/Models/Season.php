<?php

namespace App\Models;

use App\Concerns\BelongsToClub;
use App\Concerns\HasPublicUlid;
use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property int $club_id
 * @property string $name
 * @property int $matches_count
 * @property SeasonStatus $status
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Club $club
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, FootballMatch> $matches
 *
 * @method static Builder<static>|Season active()
 * @method static Builder<static>|Season completed()
 * @method static SeasonFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Season extends Model
{
    use BelongsToClub, HasFactory, HasPublicUlid;

    public const int DEFAULT_MATCHES_COUNT = 15;

    protected $fillable = [
        'club_id',
        'name',
        'matches_count',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeasonStatus::class,
            'completed_at' => 'immutable_datetime',
            'matches_count' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function isActive(): bool
    {
        return $this->status === SeasonStatus::Active;
    }

    /**
     * Non-friendly, non-cancelled matches — the ones that count toward the season.
     */
    public function countableMatches(): HasMany
    {
        return $this->matches()
            ->where('is_friendly', false)
            ->whereIn('status', [MatchStatus::Upcoming, MatchStatus::InProgress, MatchStatus::Completed]);
    }

    public function playedMatchesCount(): int
    {
        return $this->countableMatches()->count();
    }

    public function completedMatchesCount(): int
    {
        return $this->matches()
            ->where('is_friendly', false)
            ->where('status', MatchStatus::Completed)
            ->count();
    }

    public function startsOn(): ?CarbonImmutable
    {
        $date = $this->countableMatches()->min('scheduled_at');

        return $date ? CarbonImmutable::parse($date) : null;
    }

    public function endsOn(): ?CarbonImmutable
    {
        $date = $this->countableMatches()->max('scheduled_at');

        return $date ? CarbonImmutable::parse($date) : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SeasonStatus::Active);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SeasonStatus::Completed);
    }
}
