<?php

namespace App\Http\Controllers;

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
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
    ) {}

    public function index(Request $request): Response
    {
        $category = $request->query('category');
        $search = $request->query('search');
        $user = auth()->user();
        $perPage = config('news.feed.per_page', 15);

        $articles = Inertia::scroll(fn () => match (true) {
            filled($search) => $this->feedService->search($search, $perPage),
            $user !== null => $this->feedService->getPersonalizedFeed($user, $category, $perPage),
            default => $this->feedService->getPublicFeed($category, $perPage),
        });

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

        return Inertia::render('news/Show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'storySourceCount' => $relatedArticles->count() + 1,
            'comments' => $comments,
            'commentsCount' => $comments->count(),
            'readingMinutes' => $this->summaryService->estimateReadingMinutes($article),
            'isBookmarked' => $user
                ? $article->interactions()
                    ->where('user_id', $user->id)
                    ->where('type', NewsInteractionType::Bookmark)
                    ->exists()
                : false,
            'canSummarize' => $article->ai_summary === null
                && $this->summaryService->hasEnoughContent($article)
                && $this->summaryService->canGenerate(),
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
