<?php

namespace App\Models;

use App\Concerns\BelongsToClub;
use App\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\VenueFactory;
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
 * @property string|null $address
 * @property string|null $map_link
 * @property string|null $notes
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Club $club
 * @property-read Collection<int, Field> $fields
 * @property-read int|null $fields_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue active()
 * @method static \Database\Factories\VenueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereMapLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Venue extends Model
{
    use BelongsToClub, HasPublicUlid;

    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'address',
        'map_link',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
