<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Pest bootstrap — binds the test case and defines shared test helpers.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create an establishment user linked to an existing seeded listing
 * (or create a new one if no seeded listing matches the name).
 *
 * Reuses pre-seeded listings (e.g., "Botanika Nature Resort") so that
 * ArrivalSeeder data is available for tests that need it.
 */
function actingAsEstablishment(string $name, string $subtitle = 'Somewhere, Davao Oriental'): User
{
    // Try to find the pre-seeded listing first (so ArrivalSeeder data works)
    $listing = Listing::query()->where('lst_name', $name)->first();

    if (! $listing) {
        $category = Category::query()->firstOrCreate(
            ['cat_name' => 'Accommodation'],
            ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
        );

        $listing = Listing::query()->create([
            'lst_slug' => Str::slug($name.'-'.Str::random(6)),
            'lst_name' => $name,
            'lst_category' => 'accommodation',
            'cat_id' => $category->cat_id,
            'lst_municipality' => 'City of Mati',
            'lst_barangay' => 'Dahican',
            'lst_status' => 'DRAFT',
        ]);
    }

    return User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $name,
        'usr_organization_subtitle' => $subtitle,
        'lst_id' => $listing->lst_id,
    ]);
}
