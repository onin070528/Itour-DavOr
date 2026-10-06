<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the demo front-desk arrivals for Botanika Nature Resort.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\Listing;
use App\Support\EstablishmentMockData;
use Illuminate\Database\Seeder;

class ArrivalSeeder extends Seeder
{
    /**
     * Moves Botanika Nature Resort's demo front-desk arrivals — the only
     * establishment App\Support\EstablishmentMockData::seedArrivals() has
     * rows for — into the real `tbl_arrivals` table (source: staff), verbatim.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('ArrivalSeeder: skipped — refusing to seed demo arrivals in production.');

            return;
        }

        $objListing = Listing::query()->where('lst_name', 'Botanika Nature Resort')->first();

        if (! $objListing) {
            return;
        }

        $objListing->arrivals()->where('arr_source', 'staff')->delete();

        $objListing->arrivals()->createMany(
            collect(EstablishmentMockData::seedArrivals($objListing->lst_name))->map(fn (array $arrRow) => [
                'arr_source' => 'staff',
                'arr_date' => $arrRow['date'],
                'arr_visitor_name' => $arrRow['visitorName'],
                'arr_gender' => $arrRow['gender'],
                'arr_classification' => $arrRow['classification'],
                'arr_remarks' => $arrRow['remarks'],
                'arr_status' => $arrRow['status'],
                'arr_party_size' => 1,
            ])->all()
        );
    }
}
