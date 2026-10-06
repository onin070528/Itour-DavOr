<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Middleware that blocks LGU pages for accounts missing an assigned municipality.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLguHasMunicipality
{
    /**
     * Every LGU page is scoped to the account's assigned municipality
     * (mun_id — the RBAC-authoritative FK; usr_organization_subtitle
     * stays as the display label only). If that's somehow missing — an
     * incomplete account setup rather than anything the user did — fail
     * with a clear message instead of a 500 from a null municipality
     * reaching the data layer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            blank($request->user()?->mun_id),
            403,
            'Your account has no assigned municipality yet. Contact your Provincial Tourism Office administrator.'
        );

        return $next($request);
    }
}
