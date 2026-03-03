<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\AttachmentCollection;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read string|null $photo_url
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
class PlayerProfile extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerProfileFactory> */
    use HasAttachments, HasFactory;

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

    public function getPhotoUrlAttribute(): ?string
    {
        $attachment = $this->getAttachment(AttachmentCollection::Photo);

        return $attachment?->url;
    }
}
