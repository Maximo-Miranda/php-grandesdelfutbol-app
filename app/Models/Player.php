<?php

namespace App\Models;

use App\Concerns\BelongsToClub;
use App\Enums\PlayerPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $club_id
 * @property int|null $user_id
 * @property string $name
 * @property PlayerPosition|null $position
 * @property int|null $jersey_number
 * @property int $goals
 * @property int $assists
 * @property int $matches_played
 * @property int $yellow_cards
 * @property int $red_cards
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Club $club
 * @property-read string $display_name
 * @property-read string|null $photo_url
 * @property-read string|null $position_label
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player active()
 * @method static \Database\Factories\PlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player forClub(\App\Models\Club $club)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereAssists($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereGoals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereJerseyNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereMatchesPlayed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereRedCards($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereYellowCards($value)
 *
 * @mixin \Eloquent
 */
class Player extends Model
{
    use BelongsToClub;

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

    protected $appends = ['display_name', 'photo_url', 'position_label'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => PlayerPosition::class,
            'goals' => 'integer',
            'assists' => 'integer',
            'matches_played' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->playerProfile?->nickname ?? $this->name;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->user?->playerProfile?->photo_url;
    }

    public function getPositionLabelAttribute(): ?string
    {
        return $this->position?->label();
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
