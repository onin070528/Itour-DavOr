<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Application-wide service provider registration/bootstrap hook.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Providers;

use App\Models\EstablishmentImage;
use App\Models\Municipality;
use App\Models\User;
use App\Policies\ImagePolicy;
use App\Policies\MunicipalityPolicy;
use App\Support\SecurityLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Phone testing over an HTTPS tunnel (cloudflared, Objective 3 D13):
        // browsers allow geolocation only on HTTPS. In the local environment
        // only, when APP_URL is an https:// address, every link and asset URL
        // is generated from it, so pages served through the tunnel never
        // point back at http://localhost. Other environments are unaffected.
        $strAppUrl = (string) config('app.url');

        if ($this->app->environment('local') && str_starts_with($strAppUrl, 'https://')) {
            URL::forceRootUrl($strAppUrl);
            URL::forceScheme('https');
        }

        // Login brute-force throttling — keyed by email+IP so a flood of
        // attempts against one account from many IPs is still limited, and
        // one IP can't hammer many accounts unbounded either. Applied to
        // POST /login via the 'throttle:login' middleware.
        RateLimiter::for('login', function (Request $objRequest) {
            $strKey = Str::lower((string) $objRequest->input('email')).'|'.$objRequest->ip();

            return Limit::perMinute(5)->by($strKey);
        });

        // Report generation/export (PDF preview, PDF download, Excel) is
        // cheap to spam and expensive to render (DomPDF/PhpSpreadsheet) —
        // keyed by user, since every caller here is authenticated.
        RateLimiter::for('report-export', function (Request $objRequest) {
            return Limit::perMinute(20)->by($objRequest->user()?->usr_id ?: $objRequest->ip());
        });

        // Public QR self check-in — no account behind it, so keyed by IP +
        // the scanned establishment to stop one flood from blocking
        // legitimate tourists at other establishments.
        RateLimiter::for('qr-checkin', function (Request $objRequest) {
            $strKey = $objRequest->ip().'|'.(string) $objRequest->route('establishment');

            return Limit::perMinute(10)->by($strKey);
        });

        // Public Tourism Directory pages (Explore search, detail pages, Find
        // Nearby lists) — keyed by IP, and generous because a tour group
        // often shares one Wi-Fi address; it only stops scripted floods.
        RateLimiter::for('public-directory', function (Request $objRequest) {
            return Limit::perMinute(300)->by($objRequest->ip());
        });

        // Find Near Me (POST with the visitor's location) — its own limit,
        // keyed by IP only. The key never includes the submitted coordinates.
        RateLimiter::for('find-near-me', function (Request $objRequest) {
            return Limit::perMinute((int) config('tourism_directory.nearby.find_near_me_per_minute'))->by($objRequest->ip());
        });

        // Establishment photo uploads — per-user, not per-establishment, so
        // one account can't bypass the limit by spreading uploads across
        // establishments it manages.
        RateLimiter::for('establishment-image-upload', function (Request $objRequest) {
            return Limit::perMinute((int) config('establishment_images.uploads_per_minute'))->by($objRequest->user()?->usr_id ?: $objRequest->ip());
        });

        // Single source of truth for password strength — every path that
        // sets a password (change, reset) validates with Password::default()
        // instead of redeclaring the rule. Deliberately no uncompromised():
        // that check calls an external API (Have I Been Pwned) on every
        // password submission.
        // Shown to users by <x-auth.password-requirements> — keep the two in step.
        Password::defaults(fn () => Password::min(12)->letters()->numbers()->symbols());

        // ImagePolicy doesn't follow Laravel's {Model}Policy auto-discovery
        // naming convention (named ImagePolicy, not EstablishmentImagePolicy)
        // — registered explicitly instead.
        Gate::policy(EstablishmentImage::class, ImagePolicy::class);

        // MunicipalityPolicy auto-discovers fine (Municipality -> MunicipalityPolicy)
        // but is registered explicitly here for visibility alongside ImagePolicy —
        // no controller binds a single Municipality by route yet, so this is a
        // second-layer safety net for whenever one is added.
        Gate::policy(Municipality::class, MunicipalityPolicy::class);

        Gate::after(function (User $user, string $ability, ?bool $result, array $arguments): void {
            if ($result === false) {
                SecurityLogger::lguPolicyDenied($user, $ability, $arguments[0] ?? null);
            }
        });
    }
}
