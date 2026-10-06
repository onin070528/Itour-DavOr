<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Middleware that restricts a route to one or more account roles.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Restrict a route to users whose role matches one of the given roles.
     *
     * Usage: ->middleware('role:pto_administrator') or ->middleware('role:lgu,establishment')
     */
    public function handle(Request $objRequest, Closure $fnNext, string ...$roles): Response
    {
        $objUser = $objRequest->user();

        $blnAllowed = collect($roles)
            ->map(fn (string $strRole) => UserRole::from($strRole))
            ->contains($objUser?->usr_role);

        abort_unless($blnAllowed, 403);

        return $fnNext($objRequest);
    }
}
