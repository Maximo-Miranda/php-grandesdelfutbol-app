<?php

namespace App\Providers;

use App\Services\ClubContext;
use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
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
        $this->configureRateLimiters();

        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);

        Gate::define('superAdmin', fn ($user) => $user->isSuperAdmin());
        Gate::define('viewPulse', fn ($user) => $user->isSuperAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('ntfy', fn () => Limit::perMinute(200)->by('ntfy'));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => Password::min(8));
    }
}
