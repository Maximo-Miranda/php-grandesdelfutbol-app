<?php

namespace App\Models;

use App\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\NewsArticleCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $news_article_id
 * @property int $user_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read NewsArticle $article
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
class NewsArticleComment extends Model
{
    /** @use HasFactory<NewsArticleCommentFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'news_article_id',
        'user_id',
        'body',
    ];

    protected $hidden = [
        'id',
        'news_article_id',
        'user_id',
    ];

    /** @return BelongsTo<NewsArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
