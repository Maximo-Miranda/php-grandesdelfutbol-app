<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $club_id
 * @property string $email
 * @property string $token
 * @property InvitationStatus $status
 * @property int $invited_by
 * @property \Carbon\CarbonImmutable $expires_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Club $club
 * @property-read \App\Models\User $inviter
 *
 * @method static \Database\Factories\ClubInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereInvitedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubInvitation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ClubInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\ClubInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'club_id',
        'email',
        'token',
        'status',
        'invited_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', InvitationStatus::Pending);
    }

    public function scopeValid($query)
    {
        return $query->pending()->where('expires_at', '>', now());
    }
}
