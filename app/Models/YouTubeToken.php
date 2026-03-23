<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property array $token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class YouTubeToken extends Model
{
    protected $table = 'youtube_tokens';

    protected $fillable = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted:json',
        ];
    }

    public static function current(): ?self
    {
        return static::first();
    }
}
