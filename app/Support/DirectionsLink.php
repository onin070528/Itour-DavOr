<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The single builder of "Get Directions" links — an external map
 * link that carries only the destination's public coordinates.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\Listing;

/**
 * iTOUR never sends a visitor's location to a directions service: the link
 * holds the destination only (Google Maps' documented `api=1&destination=`
 * format), and the visitor's own map app works out the route on their
 * device. No origin, visitor coordinate, internal id, owner, or QR value is
 * ever part of the URL.
 */
class DirectionsLink
{
    /** Google Maps "directions" URL (Maps URLs API). */
    public const DIRECTIONS_BASE_URL = 'https://www.google.com/maps/dir/';

    /**
     * Directions to a listing's stored location, or null when it has no
     * valid location.
     */
    public static function forListing(Listing $objListing): ?string
    {
        return $objListing->hasValidCoordinates()
            ? self::toDestination((float) $objListing->lst_lat, (float) $objListing->lst_lng)
            : null;
    }

    /**
     * Directions to a destination point, or null when the point is missing
     * or outside the valid latitude/longitude range.
     */
    public static function toDestination(?float $fltLatitude, ?float $fltLongitude): ?string
    {
        $blnHasPoint = $fltLatitude !== null && $fltLongitude !== null;

        if (! $blnHasPoint) {
            return null;
        }

        $blnIsValidPoint = $fltLatitude >= -90 && $fltLatitude <= 90 && $fltLongitude >= -180 && $fltLongitude <= 180;

        if (! $blnIsValidPoint) {
            return null;
        }

        return self::DIRECTIONS_BASE_URL.'?'.http_build_query([
            'api' => 1,
            'destination' => sprintf('%.6F,%.6F', $fltLatitude, $fltLongitude),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
