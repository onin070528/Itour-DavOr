<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Application-wide service provider registration/bootstrap hook.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        // Single source of truth for password strength — every path that
        // sets a password (change, reset) validates with Password::default()
        // instead of redeclaring the rule. Deliberately no uncompromised():
        // that check calls an external API (Have I Been Pwned) on every
        // password submission.
        Password::defaults(fn () => Password::min(12)->letters()->numbers());
    }
}
