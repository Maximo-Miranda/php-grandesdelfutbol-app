<?php

namespace App\Http\Controllers;

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use App\Services\NewsFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsFeedController extends Controller
{
    public function __construct(private readonly NewsFeedService $feedService) {}

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
        $storySourceCount = $relatedArticles->count() + 1;

        return Inertia::render('news/Show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'storySourceCount' => $storySourceCount,
            'isBookmarked' => $user
                ? $article->interactions()
                    ->where('user_id', $user->id)
                    ->where('type', NewsInteractionType::Bookmark)
                    ->exists()
                : false,
        ]);
    }

    public function summarize(NewsArticle $article): RedirectResponse
    {
        if ($article->ai_summary !== null) {
            return redirect()->back();
        }

        // TODO: generate AI summary via Laravel AI SDK + Gemini
        return redirect()->back()->with('error', 'La generación de resúmenes con IA estará disponible próximamente.');
    }
}
