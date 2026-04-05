<?php

namespace App\Models;

use App\Enums\NewsInteractionType;
use Carbon\CarbonImmutable;
use Database\Factories\NewsArticleInteractionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $news_article_id
 * @property NewsInteractionType $type
 * @property int|null $time_spent_seconds
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read NewsArticle $article
 *
 * @mixin \Eloquent
 */
class NewsArticleInteraction extends Model
{
    /** @use HasFactory<NewsArticleInteractionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'news_article_id',
        'type',
        'time_spent_seconds',
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsInteractionType::class,
            'time_spent_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NewsArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
