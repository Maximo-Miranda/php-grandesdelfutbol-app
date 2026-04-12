<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use App\Enums\NewsContentType;
use Carbon\CarbonImmutable;
use Database\Factories\NewsArticleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property int $news_source_id
 * @property string|null $external_id
 * @property string $title
 * @property string|null $snippet
 * @property string|null $full_content
 * @property string|null $image_url
 * @property array<int, string>|null $image_urls
 * @property string $original_url
 * @property string|null $author
 * @property NewsContentType $content_type
 * @property string|null $video_embed_url
 * @property array<int, string>|null $tags
 * @property array<int, string>|null $competitions
 * @property array<int, string>|null $teams
 * @property array<int, string>|null $topics
 * @property bool $is_breaking
 * @property string|null $ai_summary
 * @property string|null $story_group_id
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read NewsSource $source
 * @property-read Collection<int, NewsArticleInteraction> $interactions
 * @property-read int|null $interactions_count
 *
 * @mixin \Eloquent
 */
class NewsArticle extends Model
{
    /** @use HasFactory<NewsArticleFactory> */
    use HasFactory, HasPublicUlid, HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(80);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $hidden = [
        'id',
        'news_source_id',
        'story_group_id',
    ];

    protected $fillable = [
        'news_source_id',
        'external_id',
        'title',
        'slug',
        'snippet',
        'full_content',
        'image_url',
        'image_urls',
        'original_url',
        'author',
        'content_type',
        'video_embed_url',
        'tags',
        'competitions',
        'teams',
        'topics',
        'is_breaking',
        'ai_summary',
        'story_group_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => NewsContentType::class,
            'tags' => 'array',
            'competitions' => 'array',
            'teams' => 'array',
            'topics' => 'array',
            'image_urls' => 'array',
            'is_breaking' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<NewsSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    /** @return HasMany<NewsArticleInteraction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(NewsArticleInteraction::class);
    }

    /** @return HasMany<NewsArticleComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(NewsArticleComment::class);
    }

    /** @return Collection<int, NewsArticle> */
    public function relatedArticles(): Collection
    {
        if ($this->story_group_id === null) {
            return new Collection;
        }

        return static::query()
            ->where('story_group_id', $this->story_group_id)
            ->whereKeyNot($this->id)
            ->with('source')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();
    }
}
