<?php

namespace App\Providers;

use App\Support\Tenancy\AuthenticatedUserTenantResolver;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(TenantResolver::class, AuthenticatedUserTenantResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters, applied via the `throttle:<name>` middleware.
     * Keyed by email+IP (not just IP) for login/2FA so one noisy IP can't
     * lock out every account behind it, and so credential-stuffing across
     * many accounts from one IP still gets throttled per-account.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('two-factor', fn ($request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('pos-checkout', fn ($request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        // A dedicated, higher-ceiling bucket for the mobile POS API — see
        // docs/architecture/06-api-design.md §6.1: active selling needs a
        // much higher request rate than the general 60/min `api` limiter.
        RateLimiter::for('pos-api', fn ($request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
