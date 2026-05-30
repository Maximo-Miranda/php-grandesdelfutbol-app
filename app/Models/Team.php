<?php

namespace App\Models;

use App\Concerns\BelongsToClub;
use App\Concerns\HasAttachments;
use App\Concerns\HasPublicUlid;
use App\Enums\AttachmentCollection;
use Carbon\CarbonImmutable;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $club_id
 * @property int $season_id
 * @property string $name
 * @property string $normalized_name
 * @property string $color
 * @property int|null $coach_player_id
 * @property int|null $captain_player_id
 * @property string|null $bio
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Club $club
 * @property-read Season $season
 * @property-read Player|null $coach
 * @property-read Player|null $captain
 * @property-read Collection<int, Player> $players
 * @property-read Collection<int, FootballMatch> $matchesAsA
 * @property-read Collection<int, FootballMatch> $matchesAsB
 * @property-read string|null $logo_url
 * @property-read string|null $cover_url
 *
 * @method static TeamFactory factory($count = null, $state = [])
 * @method static Builder<static>|Team forSeason(Season $season)
 *
 * @mixin \Eloquent
 */
class Team extends Model
{
    use BelongsToClub, HasAttachments, HasFactory, HasPublicUlid;

    protected $fillable = [
        'club_id',
        'season_id',
        'name',
        'normalized_name',
        'color',
        'coach_player_id',
        'captain_player_id',
        'bio',
        'is_tournament',
    ];

    protected $appends = ['logo_url', 'cover_url'];

    /**
     * Mirrors the migration default so new in-memory instances are never null.
     */
    protected $attributes = [
        'is_tournament' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_tournament' => 'boolean',
        ];
    }

    public static function normalize(string $name): string
    {
        return Str::of($name)->ascii()->lower()->squish()->toString();
    }

    protected static function booted(): void
    {
        static::saving(function (Team $team) {
            if ($team->isDirty('name') || empty($team->normalized_name)) {
                $team->normalized_name = self::normalize($team->name);
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'coach_player_id');
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'captain_player_id');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'team_player')->withTimestamps();
    }

    /**
     * Detach the given player IDs from every sibling team OF THE SAME KIND in the season.
     * Exclusivity is enforced within each bucket independently: a player can only be on one
     * non-tournament team and on one tournament team. The two buckets never affect each other,
     * so belonging to a tournament team never removes the player from a non-tournament one.
     *
     * @param  array<int>|int  $playerIds
     */
    public function detachPlayersFromSiblings(array|int $playerIds): void
    {
        $ids = array_values(array_unique(array_filter((array) $playerIds)));
        if (empty($ids)) {
            return;
        }

        $siblingTeamIds = static::query()
            ->where('season_id', $this->season_id)
            ->where('is_tournament', $this->is_tournament)
            ->where('id', '!=', $this->id)
            ->pluck('id');

        if ($siblingTeamIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ids, $siblingTeamIds) {
            DB::table('team_player')
                ->whereIn('player_id', $ids)
                ->whereIn('team_id', $siblingTeamIds)
                ->delete();

            static::query()
                ->whereIn('id', $siblingTeamIds)
                ->whereIn('coach_player_id', $ids)
                ->update(['coach_player_id' => null]);

            static::query()
                ->whereIn('id', $siblingTeamIds)
                ->whereIn('captain_player_id', $ids)
                ->update(['captain_player_id' => null]);
        });
    }

    /**
     * Attach a player to this team. Non-tournament teams first remove the player from
     * any other non-tournament team in the season. Tournament teams just attach.
     */
    public function attachPlayerExclusively(int $playerId): void
    {
        $this->detachPlayersFromSiblings([$playerId]);

        if (! $this->players()->where('players.id', $playerId)->exists()) {
            $this->players()->attach($playerId);
        }
    }

    public function matchesAsA(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'team_a_id');
    }

    public function matchesAsB(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'team_b_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->getAttachment(AttachmentCollection::TeamLogo)?->url;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->getAttachment(AttachmentCollection::TeamCover)?->url;
    }

    public function scopeForSeason(Builder $query, Season $season): Builder
    {
        return $query->where('season_id', $season->id);
    }
}
