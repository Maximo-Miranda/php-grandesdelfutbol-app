<?php

use App\Http\Middleware\EnsureClubAdmin;
use App\Http\Middleware\EnsureClubMember;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetClubContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpFoundation\Exception\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('matches:process-schedules')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('matches:notify-registration-open')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetClubContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'club.member' => EnsureClubMember::class,
            'club.admin' => EnsureClubAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, $request) {
            $maxMb = (int) (ini_get('post_max_size') ? rtrim(ini_get('post_max_size'), 'M') : 0);
            $message = "El archivo que intentaste subir es demasiado grande. Tamaño máximo: {$maxMb} MB.";

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return back()->withErrors(['file' => $message])->withInput();
            }

            return back()->withErrors(['file' => $message]);
        });
    })->create();
