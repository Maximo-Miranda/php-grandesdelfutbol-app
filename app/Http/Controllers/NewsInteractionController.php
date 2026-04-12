<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Services\NewsFeedService;
use Illuminate\Http\RedirectResponse;

class NewsInteractionController extends Controller
{
    public function __construct(private readonly NewsFeedService $feedService) {}

    public function bookmark(NewsArticle $article): RedirectResponse
    {
        $this->feedService->toggleBookmark(auth()->user(), $article);

        return back();
    }

    public function like(NewsArticle $article): RedirectResponse
    {
        $this->feedService->toggleLike(auth()->user(), $article);

        return back();
    }

    public function share(NewsArticle $article): RedirectResponse
    {
        $this->feedService->recordShare(auth()->user(), $article);

        return back();
    }
}
