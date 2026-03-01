<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\AttachmentCollection;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
