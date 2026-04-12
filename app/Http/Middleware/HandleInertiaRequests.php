<?php

namespace App\Http\Middleware;

use App\Models\NewsArticle;
use App\Models\User;
use App\Services\ClubContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user?->loadMissing('playerProfile'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentClub' => fn () => app(ClubContext::class)->get(),
            'currentMemberRole' => function () use ($user) {
                $club = app(ClubContext::class)->get();

                if (! $user || ! $club) {
                    return null;
                }

                $role = $club->getMembership($user)?->role->value;

                if (! $role && $user->isSuperAdmin()) {
                    return 'super_admin';
                }

                return $role;
            },
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'googleAuthEnabled' => config('services.google.enabled'),
            'newsUnreadCount' => fn () => $this->newsUnreadCount($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Count articles published since the user last opened the news feed.
     * Users who have never visited the feed fall back to the last 24 hours so
     * the badge has meaningful initial content.
     *
     * @return array{count: int, hasBreaking: bool}
     */
    private function newsUnreadCount(?User $user): array
    {
        if ($user === null) {
            return ['count' => 0, 'hasBreaking' => false];
        }

        $since = $user->news_last_seen_at ?? now()->subDay();
        $articlesSince = NewsArticle::query()->where('published_at', '>', $since);

        $count = $articlesSince->count();

        if ($count === 0) {
            return ['count' => 0, 'hasBreaking' => false];
        }

        return [
            'count' => $count,
            'hasBreaking' => (clone $articlesSince)->where('is_breaking', true)->exists(),
        ];
    }
}
