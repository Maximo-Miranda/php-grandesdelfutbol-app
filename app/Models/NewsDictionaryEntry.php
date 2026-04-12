<?php

namespace App\Models;

use App\Enums\NewsDictionaryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property NewsDictionaryType $type
 * @property string $key
 * @property string $label
 * @property array<int, string> $aliases
 * @property bool $is_active
 * @property string $source
 * @property int $matches_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @mixin \Eloquent
 */
class NewsDictionaryEntry extends Model
{
    protected $fillable = [
        'type',
        'key',
        'label',
        'aliases',
        'is_active',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsDictionaryType::class,
            'aliases' => 'array',
            'is_active' => 'boolean',
            'matches_count' => 'integer',
        ];
    }

    /**
     * Get all active entries grouped by type as a dictionary.
     * Cached for 1 hour, invalidated on save/delete.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function getDictionary(): array
    {
        return Cache::remember('news_dictionary', 3600, function () {
            $entries = static::where('is_active', true)->get();

            $dictionary = [];

            foreach ($entries as $entry) {
                $dictionary[$entry->type->value][$entry->key] = $entry->aliases;
            }

            return $dictionary;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('news_dictionary');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
