<?php

namespace App\Providers;

use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Observers\FootballMatchObserver;
use App\Observers\MatchAttendanceObserver;
use App\Services\ClubContext;
use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClubContext::class);

        $this->app->singleton(S3Client::class, function () {
            /** @var array<string, mixed> $disk */
            $disk = config('filesystems.disks.s3');

            $config = [
                'region' => $disk['region'],
                'version' => 'latest',
                'credentials' => [
                    'key' => $disk['key'],
                    'secret' => $disk['secret'],
                ],
            ];

            if (! empty($disk['endpoint'])) {
                $config['endpoint'] = $disk['endpoint'];
                $config['use_path_style_endpoint'] = $disk['use_path_style_endpoint'] ?? false;
            }

            return new S3Client($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);

        Gate::define('superAdmin', fn ($user) => $user->isSuperAdmin());
        Gate::define('viewPulse', fn ($user) => $user->isSuperAdmin());

        FootballMatch::observe(FootballMatchObserver::class);
        MatchAttendance::observe(MatchAttendanceObserver::class);
    }

    protected function configureRateLimiting(): void
    {
        $testing = app()->runningUnitTests();

        $limiters = [
            'google-api' => 30,
            'send-email' => 5,
            'expensive-action' => 10,
            'attendance-write' => 60,
            'attendance-admin' => 60,
            'auth-sensitive' => 3,
            'news-read' => 60,
            'news-like' => 60,
            'news-interact' => 30,
            'news-comment' => 20,
        ];

        foreach ($limiters as $name => $maxAttempts) {
            RateLimiter::for($name, function (Request $request) use ($testing, $maxAttempts) {
                return $testing ? Limit::none() : Limit::perMinute($maxAttempts)->by($request->user()?->id ?: $request->ip());
            });
        }

        RateLimiter::for('public-form', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('gemini-api', fn () => Limit::perMinute(
            (int) config('news.ai.per_minute_limit', 200),
        ));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(8));
    }
}
