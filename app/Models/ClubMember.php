<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ClubMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $club_id
 * @property int $user_id
 * @property ClubMemberRole $role
 * @property ClubMemberStatus $status
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Club $club
 * @property-read User $user
 *
 * @method static \Database\Factories\ClubMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ClubMember extends Model
{
    /** @use HasFactory<ClubMemberFactory> */
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'club_id',
        'user_id',
        'role',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ClubMemberRole::class,
            'status' => ClubMemberStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === ClubMemberRole::Owner;
    }

    public function isAdmin(): bool
    {
        return $this->role === ClubMemberRole::Admin;
    }

    public function isAtLeastAdmin(): bool
    {
        return $this->role === ClubMemberRole::Admin || $this->role === ClubMemberRole::Owner;
    }
}
