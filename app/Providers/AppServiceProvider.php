<?php

namespace App\Providers;

use App\Enums\UserStatus;
use App\Models\SiteSettings;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Every starter model uses numeric primary keys. Constraining the
        // shared {record} parameter globally makes a non-numeric record URL
        // fail routing with a 404 instead of reaching PostgreSQL, whose
        // strict bigint typing would turn it into a 500. Registered in
        // register() — not boot() — because global patterns only apply to
        // routes declared AFTER the call, and Filament's panel routes are
        // declared while package providers boot, before this provider's
        // own boot() runs.
        Route::pattern('record', '[0-9]+');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only an ACTIVE super_admin bypasses ability checks: an inactive or
        // invited super administrator must not retain global authorization
        // through a lingering session or a non-panel route. Returning null
        // (never false) keeps normal policy evaluation for everyone else.
        Gate::before(function ($user, string $ability): ?bool {
            return $user instanceof User
                && $user->status === UserStatus::Active
                && $user->hasRole('super_admin')
                    ? true
                    : null;
        });

        // Site settings (custom code snippets + booking URL) are writable
        // by NO role: the ability is defined false and only the active
        // super-admin Gate::before override above can grant it. There is no
        // permission row to misconfigure in the panel.
        Gate::define('manage-site-settings', fn (User $user): bool => false);

        // The settings row is exposed ONLY to the public layout — the
        // admin panel renders Filament's own layouts and never receives
        // this composer, so snippets cannot appear on the admin host.
        View::composer('layouts.public', function (\Illuminate\View\View $view): void {
            $view->with('siteSettings', SiteSettings::current());
        });

        Password::defaults(fn (): Password => Password::min(12)->letters()->numbers());

        // Invitation acceptance endpoints are public: throttle per client IP.
        RateLimiter::for('invitation', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Public form submissions: same per-IP posture.
        RateLimiter::for('forms', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
