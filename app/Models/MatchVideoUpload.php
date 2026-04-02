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
 * @property CarbonImmutable|null $youtube_upload_requested_at
 * @property string|null $s3_path
 * @property string|null $original_s3_path
 * @property string|null $drive_file_id
 * @property string|null $drive_reels_file_id
 * @property string|null $s3_reels_path
 * @property CarbonImmutable|null $s3_reels_uploaded_at
 * @property CarbonImmutable|null $drive_shared_at
 * @property string|null $best_resolution
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
        'drive_embed_url',
    ];

    protected $fillable = [
        'football_match_id',
        'uploaded_by',
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
        'youtube_upload_requested_at',
        's3_path',
        'original_s3_path',
        's3_reels_path',
        's3_reels_uploaded_at',
        'drive_file_id',
        'drive_reels_file_id',
        'drive_shared_at',
        'best_resolution',
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
            'youtube_upload_requested_at' => 'immutable_datetime',
            's3_reels_uploaded_at' => 'immutable_datetime',
            'drive_shared_at' => 'immutable_datetime',
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
        return $this->youtube_embed_url ?? $this->drive_embed_url;
    }

    public function getDriveEmbedUrlAttribute(): ?string
    {
        return $this->drive_file_id && $this->drive_shared_at
            ? "https://drive.google.com/file/d/{$this->drive_file_id}/preview"
            : null;
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
