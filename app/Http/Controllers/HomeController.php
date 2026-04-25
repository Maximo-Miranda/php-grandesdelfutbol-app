<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __invoke(ClubService $clubService): Response|RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return Inertia::render('Welcome', [
                'canRegister' => Features::enabled(Features::registration()),
                'appUrl' => config('app.url'),
                'recentNews' => NewsArticle::query()
                    ->with('source:id,name')
                    ->whereNotNull('published_at')
                    ->orderByDesc('published_at')
                    ->limit(6)
                    ->get(['id', 'ulid', 'slug', 'title', 'snippet', 'image_url', 'image_urls', 'published_at', 'is_breaking', 'news_source_id']),
            ]);
        }

        $club = $clubService->resolveForUser($user, $user->last_club_id);

        if ($club) {
            return redirect()->route('clubs.show', $club);
        }

        return redirect()->route('clubs.index');
    }
}
