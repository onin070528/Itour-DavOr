<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the municipalities of Davao Oriental.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class MunicipalitySeeder extends Seeder
{
    /**
     * The real, id-backed municipalities table — reuses the 11 names from
     * App\Support\TourismCatalog::municipalities() (the existing "full
     * Davao Oriental dataset"), which stays as static map-display data
     * (name + pin coordinates) and is unaffected by this table existing.
     * Always runs, in every environment — this is structural data, not demo
     * data (see RbacDemoAccountSeeder for the production-gated part).
     */
    public function run(): void
    {
        $arrRows = [
            ['Boston', 'BOS'],
            ['Cateel', 'CAT'],
            ['Baganga', 'BAG'],
            ['Caraga', 'CAR'],
            ['Manay', 'MAN'],
            ['City of Mati', 'MATI'],
            ['Tarragona', 'TAR'],
            ['San Isidro', 'SAN'],
            ['Governor Generoso', 'GOV'],
            ['Lupon', 'LUP'],
            ['Banaybanay', 'BNB'],
        ];

        foreach ($arrRows as [$strName, $code]) {
            Municipality::query()->updateOrCreate(['mun_code' => $code], ['mun_name' => $strName]);
        }
    }
}
