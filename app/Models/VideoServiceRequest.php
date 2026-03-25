<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\VideoServiceRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $user_id
 * @property int|null $match_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $club_name
 * @property string|null $venue_address
 * @property CarbonImmutable|null $preferred_date
 * @property string|null $preferred_time
 * @property string|null $message
 * @property string|null $selected_plan
 * @property VideoServiceRequestStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class VideoServiceRequest extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'user_id',
        'match_id',
        'name',
        'email',
        'phone',
        'club_name',
        'venue_address',
        'preferred_date',
        'preferred_time',
        'message',
        'selected_plan',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    protected function casts(): array
    {
        return [
            'status' => VideoServiceRequestStatus::class,
            'preferred_date' => 'date',
        ];
    }
}
