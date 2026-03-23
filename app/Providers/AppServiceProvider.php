<?php

namespace App\Providers;

use App\Services\ClubContext;
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
