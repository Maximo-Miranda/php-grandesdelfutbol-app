<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\AttachmentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    /** @use HasFactory<\Database\Factories\ClubFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'invite_token',
        'is_invite_active',
        'requires_approval',
    ];

    protected function casts(): array
    {
        return [
            'is_invite_active' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ClubInvitation::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('status', 'approved');
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        $attachment = $this->getAttachment(AttachmentCollection::Logo);

        return $attachment?->url;
    }
}
