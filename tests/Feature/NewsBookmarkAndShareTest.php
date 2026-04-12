<?php

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Models\NewsArticleComment;
use App\Models\NewsArticleInteraction;
use App\Models\NewsSource;
use App\Models\User;

function freshArticle(): NewsArticle
{
    return NewsArticle::factory()->create([
        'news_source_id' => NewsSource::factory()->create()->id,
        'published_at' => now(),
    ]);
}

test('guests cannot bookmark an article', function () {
    $article = freshArticle();

    $this->post(route('news.bookmark', $article))
        ->assertRedirect(route('login'));

    expect(NewsArticleInteraction::count())->toBe(0);
});

test('authenticated user can bookmark an article', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    $this->actingAs($user)
        ->post(route('news.bookmark', $article))
        ->assertRedirect();

    expect(NewsArticleInteraction::where('user_id', $user->id)
        ->where('news_article_id', $article->id)
        ->where('type', NewsInteractionType::Bookmark)
        ->exists())->toBeTrue();
});

test('posting bookmark twice removes it (toggle)', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    $this->actingAs($user)->post(route('news.bookmark', $article));
    $this->actingAs($user)->post(route('news.bookmark', $article));

    expect(NewsArticleInteraction::where('user_id', $user->id)
        ->where('news_article_id', $article->id)
        ->where('type', NewsInteractionType::Bookmark)
        ->exists())->toBeFalse();
});

test('article show exposes isBookmarked for auth user', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    NewsArticleInteraction::factory()
        ->for($user)
        ->for($article, 'article')
        ->bookmark()
        ->create();

    $this->actingAs($user)
        ->get(route('news.show', $article))
        ->assertInertia(fn ($page) => $page->where('isBookmarked', true));
});

test('bookmarked feed lists only bookmarked articles', function () {
    $user = User::factory()->create();
    $bookmarked = freshArticle();
    $notBookmarked = freshArticle();

    NewsArticleInteraction::factory()
        ->for($user)
        ->for($bookmarked, 'article')
        ->bookmark()
        ->create();

    $this->actingAs($user)
        ->get(route('news.bookmarks'))
        ->assertInertia(fn ($page) => $page
            ->component('news/Bookmarks')
            ->has('articles.data', 1)
            ->where('articles.data.0.ulid', $bookmarked->ulid)
        );
});

test('guests cannot share an article', function () {
    $article = freshArticle();

    $this->post(route('news.share', $article))
        ->assertRedirect(route('login'));
});

test('authenticated user can record a share', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    $this->actingAs($user)
        ->post(route('news.share', $article))
        ->assertRedirect();

    expect(NewsArticleInteraction::where('user_id', $user->id)
        ->where('news_article_id', $article->id)
        ->where('type', NewsInteractionType::Share)
        ->exists())->toBeTrue();
});

test('guests cannot like an article', function () {
    $article = freshArticle();

    $this->post(route('news.like', $article))
        ->assertRedirect(route('login'));

    expect(NewsArticleInteraction::count())->toBe(0);
});

test('authenticated user can like an article', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    $this->actingAs($user)
        ->post(route('news.like', $article))
        ->assertRedirect();

    expect(NewsArticleInteraction::where('user_id', $user->id)
        ->where('news_article_id', $article->id)
        ->where('type', NewsInteractionType::Like)
        ->exists())->toBeTrue();
});

test('liking twice removes the like (toggle)', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    $this->actingAs($user)->post(route('news.like', $article));
    $this->actingAs($user)->post(route('news.like', $article));

    expect(NewsArticleInteraction::where('type', NewsInteractionType::Like)->count())->toBe(0);
});

test('article show exposes likes_count and isLiked for auth user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $article = freshArticle();

    NewsArticleInteraction::factory()
        ->for($user)
        ->for($article, 'article')
        ->like()
        ->create();
    NewsArticleInteraction::factory()
        ->for($other)
        ->for($article, 'article')
        ->like()
        ->create();

    $this->actingAs($user)
        ->get(route('news.show', $article))
        ->assertInertia(fn ($page) => $page
            ->where('isLiked', true)
            ->where('likesCount', 2)
        );
});

test('feed exposes likes_count and comments_count per article', function () {
    $user = User::factory()->create();
    $article = freshArticle();

    NewsArticleInteraction::factory()
        ->for(User::factory())
        ->for($article, 'article')
        ->like()
        ->create();
    NewsArticleInteraction::factory()
        ->for(User::factory())
        ->for($article, 'article')
        ->like()
        ->create();

    NewsArticleComment::factory()
        ->for(User::factory())
        ->for($article, 'article')
        ->create();

    $this->actingAs($user)
        ->get(route('news.feed'))
        ->assertInertia(fn ($page) => $page
            ->where('articles.data.0.likes_count', 2)
            ->where('articles.data.0.comments_count', 1)
        );
});

test('cleanup command preserves bookmarked articles', function () {
    $user = User::factory()->create();

    $old = NewsArticle::factory()->create([
        'news_source_id' => NewsSource::factory()->create()->id,
        'published_at' => now()->subDays(30),
    ]);
    $oldBookmarked = NewsArticle::factory()->create([
        'news_source_id' => NewsSource::factory()->create()->id,
        'published_at' => now()->subDays(30),
    ]);

    NewsArticleInteraction::factory()
        ->for($user)
        ->for($oldBookmarked, 'article')
        ->bookmark()
        ->create();

    $this->artisan('news:cleanup', ['--days' => 7])->assertSuccessful();

    expect(NewsArticle::find($old->id))->toBeNull();
    expect(NewsArticle::find($oldBookmarked->id))->not->toBeNull();
});
