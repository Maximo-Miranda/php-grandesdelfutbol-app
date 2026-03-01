<?php

namespace App\Models;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubMember extends Model
{
    /** @use HasFactory<\Database\Factories\ClubMemberFactory> */
    use HasFactory;

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
}
