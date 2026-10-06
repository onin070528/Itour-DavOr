<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — explore page.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Support\TourismCatalog;

test('the Explore page shows the new establishment category chips', function () {
    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertSee('All');
    $response->assertSee('Tourist Destinations');
    $response->assertSee('Accommodation');
    $response->assertSee('Food &amp; Dining', false);
    $response->assertSee('Farm &amp; Agri-Tourism', false);
    $response->assertSee('Wellness &amp; Spa', false);
    $response->assertSee('Travel &amp; Tours', false);
    $response->assertSee('Tourist Transport');
    $response->assertSee('Recreation &amp; Activities', false);
    $response->assertSee('MICE &amp; Events', false);
    $response->assertSee('Others');
});

test('the Explore page no longer shows the old category chip labels', function () {
    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertDontSee('Restaurants');
    $response->assertDontSee('Transportation');
    $response->assertDontSee('Tour Guides');
    $response->assertDontSee('Local Delicacies');
});

test('the new category chips keep the existing category slugs so filtering data is unchanged', function () {
    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertSee('data-category-chip="destinations"', false);
    $response->assertSee('data-category-chip="accommodation"', false);
    $response->assertSee('data-category-chip="restaurants"', false);
    $response->assertSee('data-category-chip="transportation"', false);
    $response->assertSee('data-category-chip="tour-guides"', false);
    $response->assertSee('data-category-chip="local-delicacies"', false);
});

test('establishment registration still uses the original, unrenamed category list', function () {
    // Lgu\UsersController::establishmentCategories() filters
    // TourismCatalog::categories() — must stay untouched by the Explore-only
    // relabeling in exploreCategories().
    expect(collect(TourismCatalog::categories())->pluck('label')->all())
        ->toBe(['Tourist Destinations', 'Accommodation', 'Restaurants', 'Transportation', 'Tour Guides', 'Local Delicacies']);
});
