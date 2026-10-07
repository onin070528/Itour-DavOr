<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Middleware that holds an account on the first-login password-change
 * page until it replaces its temporary password.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * The only routes a signed-in account with usr_must_change_password may
     * reach: the change-password page itself and sign-out.
     */
    private const ALLOWED_ROUTES = ['password.change', 'password.change.store', 'logout'];

    /**
     * Appended to the whole `web` group (bootstrap/app.php), not to
     * individual route groups, so no page — dashboard, QR, report, or one
     * added later — can be reached by typing its URL. Guests and accounts
     * without the flag pass straight through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $objUser = $request->user();

        $blnMustChangePassword = $objUser !== null && $objUser->mustChangePassword();

        if (! $blnMustChangePassword || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, 'You must change your temporary password before continuing.');

        return redirect()->route('password.change');
    }
}
