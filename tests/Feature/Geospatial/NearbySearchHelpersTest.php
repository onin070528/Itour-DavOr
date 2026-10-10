<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Objective 3, Phase 3 — the parts of the nearby search that need
 * no SQL distance (radius handling, distance labels, GPS rounding, the
 * Davao Oriental guard, coordinate validity, input guards), so they run in
 * the regular SQLite suite. The PostgreSQL Haversine tests are in
 * NearbySearchServiceTest (group "geospatial").
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Models\Listing;
use App\Rules\WithinDavaoOrientalBounds;
use App\Services\NearbySearchService;

test('the radius is the 10 km default when missing or not positive, and never more than 50 km', function () {
    $objService = app(NearbySearchService::class);

    expect($objService->resolveRadiusKm(null))->toBe(10.0);
    expect($objService->resolveRadiusKm(0))->toBe(10.0);
    expect($objService->resolveRadiusKm(-3))->toBe(10.0);
    expect($objService->resolveRadiusKm(25))->toBe(25.0);
    expect($objService->resolveRadiusKm(50))->toBe(50.0);
    expect($objService->resolveRadiusKm(51))->toBe(50.0);
    expect(config('tourism_directory.nearby.radius_options_km'))->toBe([1, 5, 10, 25, 50]);
});

test('distances read "450 m away" under 1 km and "1.2 km away" otherwise', function () {
    $objService = app(NearbySearchService::class);

    expect($objService->formatDistance(0.45))->toBe('450 m away');
    expect($objService->formatDistance(0.4534))->toBe('450 m away');
    expect($objService->formatDistance(0.0))->toBe('10 m away');
    expect($objService->formatDistance(0.994))->toBe('990 m away');
    expect($objService->formatDistance(0.996))->toBe('1.0 km away');
    expect($objService->formatDistance(1.24))->toBe('1.2 km away');
    expect($objService->formatDistance(49.67))->toBe('49.7 km away');
});

test('a tourist coordinate is rounded to 4 decimal places', function () {
    $objService = app(NearbySearchService::class);

    expect($objService->roundCoordinate(6.95504912))->toBe(6.955);
    expect($objService->roundCoordinate(126.21786))->toBe(126.2179);
    expect($objService->roundCoordinate(-7.12345))->toBe(-7.1235);
});

test('the Davao Oriental guard comes from configuration', function () {
    expect(WithinDavaoOrientalBounds::isInside(6.9578, 126.2478))->toBeTrue();
    expect(WithinDavaoOrientalBounds::isInside(6.20, 125.80))->toBeTrue();
    expect(WithinDavaoOrientalBounds::isInside(8.10, 126.70))->toBeTrue();
    expect(WithinDavaoOrientalBounds::isInside(14.5995, 120.9842))->toBeFalse();
    expect(WithinDavaoOrientalBounds::isInside(6.9578, 126.80))->toBeFalse();

    config(['tourism_directory.coordinate_bounds.max_longitude' => 127.0]);
    expect(WithinDavaoOrientalBounds::isInside(6.9578, 126.80))->toBeTrue();
});

test('a listing has valid coordinates only when both are set and in range', function () {
    $fnListing = fn (?float $fltLatitude, ?float $fltLongitude) => new Listing(['lst_lat' => $fltLatitude, 'lst_lng' => $fltLongitude]);

    expect($fnListing(6.9578, 126.2478)->hasValidCoordinates())->toBeTrue();
    expect($fnListing(null, 126.2478)->hasValidCoordinates())->toBeFalse();
    expect($fnListing(6.9578, null)->hasValidCoordinates())->toBeFalse();
    expect($fnListing(95.0, 126.2478)->hasValidCoordinates())->toBeFalse();
    expect($fnListing(6.9578, 190.0)->hasValidCoordinates())->toBeFalse();
});

test('an invalid reference point, or a listing without a location, is rejected before any query', function () {
    $objService = app(NearbySearchService::class);

    expect(fn () => $objService->findNearbyListings(95, 126.2, 10))->toThrow(InvalidArgumentException::class);
    expect(fn () => $objService->findNearbyListings(6.9, 181, 10))->toThrow(InvalidArgumentException::class);
    expect(fn () => $objService->calculateDistanceKm(6.9, 126.2, -91, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $objService->findNearbyListingsAround(new Listing(['lst_lat' => null, 'lst_lng' => null])))->toThrow(InvalidArgumentException::class);
});
