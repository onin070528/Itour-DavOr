<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Root seeder — runs every application seeder in dependency order.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Model events stay enabled on purpose: Listing's creating hook
     * assigns every new listing its QR check-in uuid.
     */
    public function run(): void
    {
        $this->call([
            MunicipalitySeeder::class,
            UserSeeder::class,
            ListingSeeder::class,
            CategorySeeder::class,
            ArrivalSeeder::class,
            MunicipalReportSeeder::class,
            RbacScopeBackfillSeeder::class,
            RbacDemoAccountSeeder::class,
        ]);
    }
}
