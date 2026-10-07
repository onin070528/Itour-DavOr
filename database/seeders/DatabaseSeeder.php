<?php

namespace Database\Seeders;

use App\Models\Listing;
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
            CategorySeeder::class,
            ArrivalSeeder::class,
            MunicipalReportSeeder::class,
            RbacScopeBackfillSeeder::class,
            RbacDemoAccountSeeder::class,
        ]);

        // WithoutModelEvents above also mutes Listing's creating hook that
        // assigns each listing its check-in uuid — fill them in here so a
        // fresh seed never leaves every QR code unusable.
        Listing::backfillMissingUuids();
    }
}
