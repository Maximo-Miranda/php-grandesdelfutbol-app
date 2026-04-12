<?php

use App\Models\NewsArticle;
use App\Models\NewsArticleComment;
use App\Models\NewsSource;
use App\Models\User;

test('guests cannot post comments', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

    $this->post(route('news.comments.store', $article), ['body' => 'Gran partido'])
        ->assertRedirect(route('login'));
});

test('authenticated user can post a comment', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

    $this->actingAs($user)
        ->post(route('news.comments.store', $article), ['body' => 'Excelente análisis'])
        ->assertRedirect();

    $this->assertDatabaseHas('news_article_comments', [
        'news_article_id' => $article->id,
        'user_id' => $user->id,
        'body' => 'Excelente análisis',
    ]);
});

test('comment body is required and must be within length limits', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);

    $this->actingAs($user)
        ->post(route('news.comments.store', $article), ['body' => ''])
        ->assertSessionHasErrors('body');

    $this->actingAs($user)
        ->post(route('news.comments.store', $article), ['body' => str_repeat('a', 1001)])
        ->assertSessionHasErrors('body');
});

test('user can delete their own comment', function () {
    $user = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);
    $comment = NewsArticleComment::factory()->create([
        'news_article_id' => $article->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('news.comments.destroy', [$article, $comment]))
        ->assertRedirect();

    $this->assertDatabaseMissing('news_article_comments', ['id' => $comment->id]);
});

test('user cannot delete another users comment', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);
    $comment = NewsArticleComment::factory()->create([
        'news_article_id' => $article->id,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->delete(route('news.comments.destroy', [$article, $comment]))
        ->assertForbidden();

    $this->assertDatabaseHas('news_article_comments', ['id' => $comment->id]);
});

test('show page returns comments for the article', function () {
    $source = NewsSource::factory()->create();
    $article = NewsArticle::factory()->create(['news_source_id' => $source->id]);
    NewsArticleComment::factory()->count(3)->create(['news_article_id' => $article->id]);

    $this->get(route('news.show', $article))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('comments', 3)
            ->where('commentsCount', 3)
        );
});
