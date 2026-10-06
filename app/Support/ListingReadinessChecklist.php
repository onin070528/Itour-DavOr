<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The "Ready to publish" checklist an establishment's package must
 * pass before it can be submitted to the LGU — a plain-English list of
 * whichever required fields are still missing.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\Listing;

class ListingReadinessChecklist
{
    /**
     * Returns a plain-English label for each missing field — name,
     * category, description, and a public phone OR email (either one
     * satisfies that requirement). An empty array means the package is
     * ready to submit.
     *
     * @return array<int, string>
     */
    public static function missingFields(Listing $objListing): array
    {
        $arrMissing = [];

        if (blank($objListing->lst_name)) {
            $arrMissing[] = 'Name';
        }

        if (blank($objListing->lst_category)) {
            $arrMissing[] = 'Category';
        }

        if (blank($objListing->lst_description)) {
            $arrMissing[] = 'Description';
        }

        if (blank($objListing->lst_contact_phone) && blank($objListing->lst_email)) {
            $arrMissing[] = 'A public phone number or email';
        }

        return $arrMissing;
    }
}
