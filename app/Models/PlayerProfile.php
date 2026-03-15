<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $nickname
 * @property Gender|null $gender
 * @property \Carbon\CarbonImmutable|null $date_of_birth
 * @property string|null $id_type
 * @property string|null $id_number
 * @property string|null $nationality
 * @property string|null $bio
 * @property string|null $preferred_position
 * @property string|null $phone
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read string|null $photo_url
 * @property-read string|null $photo_thumb_url
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\PlayerProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile wherePreferredPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerProfile whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PlayerProfile extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\PlayerProfileFactory> */
    use HasFactory, InteractsWithMedia;

    protected $with = ['media'];

    protected $appends = ['photo_url', 'photo_thumb_url'];

    protected $hidden = ['media'];

    protected $fillable = [
        'user_id',
        'nickname',
        'gender',
        'date_of_birth',
        'id_type',
        'id_number',
        'nationality',
        'bio',
        'preferred_position',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->nonQueued();

        $this->addMediaConversion('avatar')
            ->fit(Fit::Crop, 300, 300)
            ->format('webp')
            ->quality(85)
            ->nonQueued();

        $this->addMediaConversion('profile')
            ->fit(Fit::Crop, 600, 600)
            ->format('webp')
            ->quality(90)
            ->queued();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->getFirstMedia('photo')) {
            return null;
        }

        return $this->getFirstTemporaryUrl(now()->addMinutes(60), 'photo', 'avatar');
    }

    public function getPhotoThumbUrlAttribute(): ?string
    {
        if (! $this->getFirstMedia('photo')) {
            return null;
        }

        return $this->getFirstTemporaryUrl(now()->addMinutes(60), 'photo', 'thumb');
    }
}
