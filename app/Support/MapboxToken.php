<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The only way a Mapbox token reaches the browser — the existing
 * config('services.mapbox.token') value, and only when it is a public pk. token.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

/**
 * Reads the existing Mapbox configuration (config/services.php ->
 * MAPBOX_SECRET_KEY) — no second configuration. Every view that renders a
 * token into a data-mapbox-token attribute calls browserToken(), so a
 * secret (sk.) or temporary (tk.) token configured by mistake is never sent
 * to a visitor: the maps fall back to their "map can't be displayed" state
 * instead. The token value itself is never logged.
 */
class MapboxToken
{
    /** Prefix of a Mapbox public (browser-safe) access token. */
    public const PUBLIC_TOKEN_PREFIX = 'pk.';

    /**
     * The configured token when it is a public pk. token, otherwise an
     * empty string (the browser then treats Mapbox as unavailable).
     */
    public static function browserToken(): string
    {
        $strToken = (string) config('services.mapbox.token');

        return str_starts_with($strToken, self::PUBLIC_TOKEN_PREFIX) ? $strToken : '';
    }
}
