<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared LGU-scoping guard — every LGU write against a specific
 * Listing must stay inside the account's own municipality. Extracted from
 * Lgu\DirectoryController (its original, sole owner) so Lgu\MonthlyReportsController
 * can reuse the exact same check instead of a second copy.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\Listing;
use App\Support\SecurityLogger;
use Illuminate\Http\Request;

trait AuthorizesOwnMunicipality
{
    /**
     * Compares the real municipality_id FK, not the display-only
     * municipality/organization_subtitle strings, so this can't be fooled
     * by a name mismatch or a listing whose FK backfill didn't resolve.
     */
    private function authorizeOwnMunicipality(Request $request, Listing $listing): void
    {
        if ($listing->municipality_id === null || $listing->municipality_id !== $request->user()->municipality_id) {
            SecurityLogger::accessDenied($request->user(), 'municipality_scope', Listing::class, $listing->municipality_id);
            abort(403);
        }
    }
}
