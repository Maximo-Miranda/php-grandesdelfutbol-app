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
     * Detach the given player IDs from every other NON-TOURNAMENT team in the same season.
     * Non-tournament teams (casual pickup) enforce exclusivity — a player can only be on one.
     * Tournament teams allow shared rosters (players can be in multiple tournament squads).
     *
     * @param  array<int>|int  $playerIds
     */
    public function detachPlayersFromSiblings(array|int $playerIds): void
    {
        if ($this->is_tournament) {
            return;
        }

        $ids = (array) $playerIds;
        if (empty($ids)) {
            return;
        }

        DB::table('team_player')
            ->whereIn('player_id', $ids)
            ->whereIn('team_id', static::query()
                ->where('season_id', $this->season_id)
                ->where('is_tournament', false)
                ->where('id', '!=', $this->id)
                ->select('id'))
            ->delete();
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
