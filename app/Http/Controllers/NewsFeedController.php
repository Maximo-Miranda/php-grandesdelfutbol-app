<?php

namespace App\Http\Controllers;

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Services\NewsBadgeService;
use App\Services\NewsFeedService;
use App\Services\NewsSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsFeedController extends Controller
{
    public function __construct(
        private readonly NewsFeedService $feedService,
        private readonly NewsSummaryService $summaryService,
        private readonly NewsBadgeService $badgeService,
    ) {}

    public function index(Request $request): Response
    {
        $category = $request->query('category');
        $search = $request->query('search');
        $user = auth()->user();
        $perPage = config('news.feed.per_page', 15);

        // "all" is a virtual category from the "Todas" pill — it means
        // "show everything, ignore my preferences". Real dictionary-based
        // categories are validated below.
        $showAll = $category === 'all';
        $filterCategory = $showAll ? null : $category;

        if (is_string($filterCategory) && ! NewsFeedService::categoryExists($filterCategory)) {
            abort(404, 'Categoría de noticias no encontrada.');
        }

        if ($user !== null) {
            $user->update(['news_last_seen_at' => now()]);
            $this->badgeService->forget($user);
        }

        $articles = Inertia::scroll(fn () => match (true) {
            filled($search) => $this->feedService->search($search, $perPage, $user),
            $showAll => $this->feedService->getPublicFeed(null, $perPage, $user),
            $user !== null => $this->feedService->getPersonalizedFeed($user, $filterCategory, $perPage),
            default => $this->feedService->getPublicFeed($filterCategory, $perPage),
        })->matchOn('data.ulid');

        return Inertia::render('news/Feed', [
            'articles' => $articles,
            'currentCategory' => $category,
            'search' => $search,
            'hasPreferences' => $user?->newsPreference?->hasPreferences() ?? false,
        ]);
    }

    public function show(NewsArticle $article): Response
    {
        $article->load('source:id,name,slug,logo_url');
        $article->loadCount([
            'comments as comments_count',
            'interactions as likes_count' => fn ($q) => $q->where('type', NewsInteractionType::Like),
        ]);

        $user = auth()->user();

        if ($user) {
            $this->feedService->recordInteraction($user, $article, NewsInteractionType::View);
        }

        $relatedArticles = $article->relatedArticles();

        $comments = $article->comments()
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get();

        $isBookmarked = false;
        $isLiked = false;

        if ($user !== null) {
            $existingTypes = $article->interactions()
                ->where('user_id', $user->id)
                ->whereIn('type', [NewsInteractionType::Bookmark, NewsInteractionType::Like])
                ->pluck('type');

            $isBookmarked = $existingTypes->contains(NewsInteractionType::Bookmark);
            $isLiked = $existingTypes->contains(NewsInteractionType::Like);
        }

        return Inertia::render('news/Show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'storySourceCount' => $relatedArticles->count() + 1,
            'comments' => $comments,
            'commentsCount' => $comments->count(),
            'readingMinutes' => $this->summaryService->estimateReadingMinutes($article),
            'isBookmarked' => $isBookmarked,
            'isLiked' => $isLiked,
            'likesCount' => $article->likes_count,
            'canSummarize' => $article->ai_summary === null
                && $this->summaryService->hasEnoughContent($article)
                && $this->summaryService->canGenerate(),
        ]);
    }

    public function bookmarks(Request $request): Response
    {
        $perPage = config('news.feed.per_page', 15);

        $articles = Inertia::scroll(
            fn () => $this->feedService->getBookmarkedFeed($request->user(), $perPage),
        )->matchOn('data.ulid');

        return Inertia::render('news/Bookmarks', [
            'articles' => $articles,
        ]);
    }

    public function summarize(NewsArticle $article): RedirectResponse
    {
        if ($article->ai_summary !== null) {
            return back();
        }

        if (! $this->summaryService->hasEnoughContent($article)) {
            return back()->with('error', 'Esta noticia no tiene suficiente contenido para generar un resumen.');
        }

        if (! $this->summaryService->canGenerate()) {
            return back()->with('error', 'Se alcanzó el límite diario de resúmenes. Intenta mañana.');
        }

        if (! $this->summaryService->summarize($article)) {
            return back()->with('error', 'No se pudo generar el resumen. Intenta de nuevo más tarde.');
        }

        return back();
    }
}
