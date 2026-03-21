<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\VideoUploadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $football_match_id
 * @property int $uploaded_by
 * @property string $bunny_video_id
 * @property VideoUploadStatus $status
 * @property string|null $original_filename
 * @property int|null $original_size_bytes
 * @property int|null $duration_seconds
 * @property int $video_offset_seconds
 * @property string|null $error_message
 * @property \Carbon\CarbonImmutable|null $uploaded_at
 * @property \Carbon\CarbonImmutable|null $encoded_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class MatchVideoUpload extends Model
{
    use HasFactory, HasPublicUlid;

    protected $appends = [
        'stream_url',
        'thumbnail_url',
        'embed_url',
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

    public function getStreamUrlAttribute(): ?string
    {
        if ($this->status !== VideoUploadStatus::Ready) {
            return null;
        }

        return 'https://'.config('bunny.cdn_hostname')."/{$this->bunny_video_id}/playlist.m3u8";
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return 'https://'.config('bunny.cdn_hostname')."/{$this->bunny_video_id}/thumbnail.jpg";
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return 'https://iframe.mediadelivery.net/embed/'.config('bunny.stream_library_id')."/{$this->bunny_video_id}";
    }
}
