<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Concerns\HasPublicUlid;
use App\Enums\AttachmentCollection;
use App\Enums\ClubMemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $description
 * @property int $owner_id
 * @property string|null $invite_token
 * @property bool $is_invite_active
 * @property bool $requires_approval
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read string|null $logo_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FootballMatch> $matches
 * @property-read int|null $matches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubMember> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Player> $players
 * @property-read int|null $players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Venue> $venues
 * @property-read int|null $venues_count
 *
 * @method static \Database\Factories\ClubFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club forUser(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereInviteToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereIsInviteActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereRequiresApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Club extends Model
{
    /** @use HasFactory<\Database\Factories\ClubFactory> */
    use HasAttachments, HasFactory, HasPublicUlid;

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

    public function getMembership(User $user): ?ClubMember
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->first();
    }

    public function isApprovedMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', ClubMemberStatus::Approved)
            ->exists();
    }

    public function isAdminOrOwner(User $user): bool
    {
        $membership = $this->getMembership($user);

        return $membership !== null && $membership->isAtLeastAdmin();
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
