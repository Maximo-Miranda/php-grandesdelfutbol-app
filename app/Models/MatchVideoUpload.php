<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\VideoUploadStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $football_match_id
 * @property int $uploaded_by
 * @property string|null $bunny_video_id
 * @property VideoUploadStatus $status
 * @property string|null $original_filename
 * @property int|null $original_size_bytes
 * @property int|null $duration_seconds
 * @property int $video_offset_seconds
 * @property string|null $error_message
 * @property CarbonImmutable|null $uploaded_at
 * @property CarbonImmutable|null $encoded_at
 * @property string|null $youtube_video_id
 * @property CarbonImmutable|null $youtube_uploaded_at
 * @property string|null $s3_path
 * @property string|null $best_resolution
 * @property CarbonImmutable|null $bunny_deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MatchVideoUpload extends Model
{
    use HasFactory, HasPublicUlid;

    protected $appends = [
        'embed_url',
        'youtube_url',
        'youtube_embed_url',
    ];

    protected $fillable = [
        'football_match_id',
        'uploaded_by',
        'bunny_video_id',
        'status',
        'original_filename',
        'original_size_bytes',
        'duration_seconds',
        'video_offset_seconds',
        'error_message',
        'uploaded_at',
        'encoded_at',
        'youtube_video_id',
        'youtube_uploaded_at',
        's3_path',
        'best_resolution',
        'bunny_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoUploadStatus::class,
            'original_size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'video_offset_seconds' => 'integer',
            'uploaded_at' => 'immutable_datetime',
            'encoded_at' => 'immutable_datetime',
            'youtube_uploaded_at' => 'immutable_datetime',
            'bunny_deleted_at' => 'immutable_datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'football_match_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return $this->youtube_embed_url;
    }

    public function getYoutubeUrlAttribute(): ?string
    {
        return $this->youtube_video_id
            ? "https://www.youtube.com/watch?v={$this->youtube_video_id}"
            : null;
    }

    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        return $this->youtube_video_id
            ? "https://www.youtube.com/embed/{$this->youtube_video_id}"
            : null;
    }
}
