<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MunicipalitySeeder::class,
            UserSeeder::class,
            ListingSeeder::class,
            ArrivalSeeder::class,
            MunicipalReportSeeder::class,
            RbacScopeBackfillSeeder::class,
            RbacDemoAccountSeeder::class,
        ]);
    }
}
