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
use App\Policies\ImagePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Login brute-force throttling — keyed by email+IP so a flood of
        // attempts against one account from many IPs is still limited, and
        // one IP can't hammer many accounts unbounded either. Applied to
        // POST /login via the 'throttle:login' middleware.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Report generation/export (PDF preview, PDF download, Excel) is
        // cheap to spam and expensive to render (DomPDF/PhpSpreadsheet) —
        // keyed by user, since every caller here is authenticated.
        RateLimiter::for('report-export', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Public QR self check-in — no account behind it, so keyed by IP +
        // the scanned establishment to stop one flood from blocking
        // legitimate tourists at other establishments.
        RateLimiter::for('qr-checkin', function (Request $request) {
            $key = $request->ip().'|'.(string) $request->route('establishment');

            return Limit::perMinute(10)->by($key);
        });

        // Establishment photo uploads — per-user, not per-establishment, so
        // one account can't bypass the limit by spreading uploads across
        // establishments it manages.
        RateLimiter::for('establishment-image-upload', function (Request $request) {
            return Limit::perMinute((int) config('establishment_images.uploads_per_minute'))->by($request->user()?->id ?: $request->ip());
        });

        // Single source of truth for password strength — every path that
        // sets a password (change, reset) validates with Password::default()
        // instead of redeclaring the rule. Deliberately no uncompromised():
        // that check calls an external API (Have I Been Pwned) on every
        // password submission.
        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        // ImagePolicy doesn't follow Laravel's {Model}Policy auto-discovery
        // naming convention (named ImagePolicy, not EstablishmentImagePolicy)
        // — registered explicitly instead.
        Gate::policy(EstablishmentImage::class, ImagePolicy::class);
    }
}
