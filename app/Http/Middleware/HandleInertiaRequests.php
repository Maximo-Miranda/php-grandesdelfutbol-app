<?php

namespace App\Http\Middleware;

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
            'uploadDriver' => config('youtube.upload_driver', 's3'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
