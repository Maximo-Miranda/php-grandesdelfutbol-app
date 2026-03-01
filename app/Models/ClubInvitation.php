<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
