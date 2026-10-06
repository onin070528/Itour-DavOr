<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Smoke test — every parameterless GET route renders without a server error for a guest and for
 * each role (PTO, LGU, Establishment), so schema or naming regressions on any page surface immediately.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Names of every GET route that needs no URL parameters and is not a framework/internal endpoint.
 *
 * @return array<int, string>
 */
function smokeRouteNames(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($objRoute) => in_array('GET', $objRoute->methods(), true))
        ->reject(fn ($objRoute) => str_contains($objRoute->uri(), '{') || $objRoute->getName() === null)
        ->reject(fn ($objRoute) => preg_match('/^(storage|ignition|sanctum|up$|debugbar)/', $objRoute->getName().'|'.$objRoute->uri()) === 1)
        ->reject(fn ($objRoute) => str_contains((string) $objRoute->getName(), 'export') || str_contains((string) $objRoute->getName(), 'pdf') || str_contains((string) $objRoute->getName(), 'excel'))
        ->map(fn ($objRoute) => $objRoute->getName())
        ->sort()
        ->values()
        ->all();
}

test('every parameterless GET route renders without a server error for each role', function (string $strRole) {
    // Seeded data (listings, arrivals, municipal reports) makes the pages render real rows, not empty states.
    test()->seed();

    expect(smokeRouteNames())->not->toBeEmpty();

    $objMati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);

    $objUser = match ($strRole) {
        'pto' => User::factory()->create(['usr_role' => UserRole::PtoAdministrator]),
        'lgu' => User::factory()->create([
            'usr_role' => UserRole::Lgu,
            'usr_organization_subtitle' => 'City of Mati',
            'mun_id' => $objMati->mun_id,
        ]),
        'establishment' => User::query()->where('usr_role', UserRole::Establishment)->whereNotNull('lst_id')->firstOrFail(),
        default => null,
    };

    if ($objUser !== null) {
        test()->actingAs($objUser);
    }

    $arrFailures = [];

    foreach (smokeRouteNames() as $strRouteName) {
        $objResponse = test()->get(route($strRouteName));
        $intStatus = $objResponse->getStatusCode();

        if ($intStatus >= 500) {
            $arrFailures[] = "{$strRouteName} => {$intStatus} ".substr((string) $objResponse->exception?->getMessage(), 0, 200);
        }
    }

    expect($arrFailures)->toBe([]);
})->with(['guest', 'pto', 'lgu', 'establishment']);
