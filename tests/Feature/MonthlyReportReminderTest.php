<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment monthly-report deadline reminders.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Support\MonthlyReportReminder;
use Carbon\CarbonImmutable;

// makeEstablishmentListing()/makeEstablishmentUser() come from
// tests/Feature/Rbac/EstablishmentScopingTest.php (shared Pest namespace).

test('the reminder stage follows the 15th-of-month deadline', function (string $strToday, ?string $strStage) {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');

    $arrReminder = MonthlyReportReminder::forListing($objListing, CarbonImmutable::parse($strToday));

    expect($arrReminder['stage'] ?? null)->toBe($strStage);
    if ($arrReminder) {
        expect($arrReminder['period']->format('Y-m'))->toBe('2026-09');
    }
})->with([
    'too early' => ['2026-10-11', null],
    'three days before' => ['2026-10-12', 'upcoming'],
    'day before' => ['2026-10-14', 'upcoming'],
    'due day' => ['2026-10-15', 'due'],
    'overdue' => ['2026-10-20', 'overdue'],
]);

test('no reminder once the month has been submitted', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objUser = makeEstablishmentUser($objListing);

    test()->actingAs($objUser)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09']);

    expect(MonthlyReportReminder::forListing($objListing, CarbonImmutable::parse('2026-10-16')))->toBeNull();
});

test('the reminder modal shows once per session on an establishment page', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00'));
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objUser = makeEstablishmentUser($objListing);

    test()->actingAs($objUser)->get(route('establishment.arrivals.monthly'))
        ->assertOk()
        ->assertSee('monthly-report-reminder-modal', false)
        ->assertSee('September 2026 report is due in 2 days');

    test()->actingAs($objUser)->get(route('establishment.arrivals.monthly'))
        ->assertOk()
        ->assertDontSee('monthly-report-reminder-modal', false);
});
