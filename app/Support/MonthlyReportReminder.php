<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Works out whether an establishment should currently be reminded
 * to submit last month's tourist-arrival report to its LGU. The report for
 * a month is due on the 15th of the following month; a heads-up is shown
 * for the three days before that, and a "due" / "overdue" notice from the
 * 15th until the report is submitted.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\Listing;
use Carbon\CarbonImmutable;

class MonthlyReportReminder
{
    public const DUE_DAY = 15;

    public const LEAD_DAYS = 3;

    /**
     * Null when no reminder applies (too early in the month, or already submitted).
     *
     * @return array{stage: string, period: CarbonImmutable, due: CarbonImmutable, daysLeft: int}|null
     */
    public static function forListing(Listing $objListing, ?CarbonImmutable $dtmNow = null): ?array
    {
        if (! $objListing->requiresArrivalRecords()) {
            return null;
        }

        $dtmToday = ($dtmNow ?? CarbonImmutable::now())->startOfDay();
        $dtmPeriod = $dtmToday->subMonthNoOverflow()->startOfMonth();
        $dtmDue = $dtmToday->startOfMonth()->day(self::DUE_DAY);
        $intDaysLeft = (int) $dtmToday->diffInDays($dtmDue, false);

        if ($intDaysLeft > self::LEAD_DAYS) {
            return null;
        }

        if ($objListing->monthlyArrivalReports()->forPeriod($dtmPeriod)->exists()) {
            return null;
        }

        return [
            'stage' => $intDaysLeft > 0 ? 'upcoming' : ($intDaysLeft === 0 ? 'due' : 'overdue'),
            'period' => $dtmPeriod,
            'due' => $dtmDue,
            'daysLeft' => $intDaysLeft,
        ];
    }
}
