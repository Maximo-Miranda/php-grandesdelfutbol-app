<?php

namespace App\Http\Controllers;

use App\Http\Requests\News\StoreNewsCommentRequest;
use App\Models\NewsArticle;
use App\Models\NewsArticleComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NewsCommentController extends Controller
{
    public function store(StoreNewsCommentRequest $request, NewsArticle $article): RedirectResponse
    {
        $article->comments()->create([
            'user_id' => auth()->id(),
            'body' => $request->validated('body'),
        ]);

        // The feed refreshes the affected card on back navigation via the
        // useNewsFeedDirty composable, which is marked from NewsComments.vue's
        // onSuccess callback. See Feed.vue's refreshIfDirty handler.
        return back();
    }

    public function destroy(NewsArticle $article, NewsArticleComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back();
    }
}
