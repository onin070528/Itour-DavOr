<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Assigns a check-in uuid to every listing that is missing one.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;

class BackfillListingUuids extends Command
{
    protected $signature = 'listings:backfill-uuids';

    protected $description = 'Assigns a check-in uuid (/checkin/{uuid}) to every listing that has none. Existing uuids are never changed, so printed QR codes keep working.';

    /**
     * Safe to run any number of times — only rows with a NULL uuid are
     * touched (see Listing::backfillMissingUuids()).
     */
    public function handle(): int
    {
        $intFilledCount = Listing::backfillMissingUuids();

        $this->info("Assigned a uuid to {$intFilledCount} listing(s).");

        return self::SUCCESS;
    }
}
