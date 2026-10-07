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
            if ($result === false && $user->isLgu()) {
                $target = $arguments[0] ?? null;
                $targetMunicipalityId = is_object($target) && isset($target->mun_id)
                    ? (int) $target->mun_id
                    : null;

                SecurityLogger::accessDenied(
                    $user,
                    $ability,
                    is_object($target) ? $target::class : null,
                    $targetMunicipalityId
                );
            }
        });
    }
}
