<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacDemoAccountSeeder extends Seeder
{
    /**
     * Seeds the 3 RBAC-foundation demo accounts (see docs/DEV_ACCOUNTS.md),
     * alongside — not instead of — UserSeeder's existing demo dataset.
     * Refuses to run in production, and refuses to run if SEED_DEMO_PASSWORD
     * isn't set, rather than falling back to a hard-coded password.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('RbacDemoAccountSeeder: skipped — refusing to seed demo accounts in production.');

            return;
        }

        $password = env('SEED_DEMO_PASSWORD');

        if (! $password) {
            $this->command?->warn('RbacDemoAccountSeeder: skipped — SEED_DEMO_PASSWORD is not set in .env.');

            return;
        }

        $mati = Municipality::query()->where('code', 'MATI')->first();
        $baganga = Municipality::query()->where('code', 'BAG')->first();

        if (! $mati || ! $baganga) {
            $this->command?->warn('RbacDemoAccountSeeder: skipped — run MunicipalitySeeder first.');

            return;
        }

        // Fresh, minimal demo establishments — deliberately separate from
        // the existing TourismCatalog-seeded listings (Botanika Nature
        // Resort etc.) so this seeder never collides with ListingSeeder's
        // data. Baganga has zero seeded listings today, so its demo
        // establishment also doubles as the cross-municipality-denial test
        // fixture used by tests/Feature/Rbac/MunicipalityScopingTest.php.
        $matiEstablishment = Listing::query()->updateOrCreate(
            ['slug' => 'itour-demo-establishment-mati'],
            [
                'name' => 'iTOUR Demo Establishment (Mati)',
                'category' => 'accommodation',
                'municipality' => 'City of Mati',
                'municipality_id' => $mati->id,
                'barangay' => 'Poblacion',
                'description' => 'RBAC demo/test fixture — not a real establishment.',
                'status' => 'Active',
            ]
        );

        Listing::query()->updateOrCreate(
            ['slug' => 'itour-demo-establishment-baganga'],
            [
                'name' => 'iTOUR Demo Establishment (Baganga)',
                'category' => 'accommodation',
                'municipality' => 'Baganga',
                'municipality_id' => $baganga->id,
                'barangay' => 'Poblacion',
                'description' => 'RBAC demo/test fixture — not a real establishment. Used to verify cross-municipality access denial.',
                'status' => 'Active',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'tourism@itourdavor.gov.ph'],
            [
                'name' => 'iTOUR PTO Demo Account',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => UserRole::PtoAdministrator,
                'organization_name' => 'Provincial Tourism Office',
                'organization_subtitle' => 'Province of Davao Oriental',
                'municipality_id' => null,
                'establishment_id' => null,
                'status' => 'Active',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'tourism.mati@itourdavor.gov.ph'],
            [
                'name' => 'iTOUR LGU Demo Account (Mati)',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => UserRole::Lgu,
                'organization_name' => 'Mati City Tourism Office',
                'organization_subtitle' => 'City of Mati',
                'municipality_id' => $mati->id,
                'establishment_id' => null,
                'status' => 'Active',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'establishments@itourdavor.gov.ph'],
            [
                'name' => 'iTOUR Establishment Demo Account',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => UserRole::Establishment,
                'organization_name' => $matiEstablishment->name,
                'organization_subtitle' => "{$matiEstablishment->barangay}, {$matiEstablishment->municipality}",
                'municipality_id' => $mati->id,
                'establishment_id' => $matiEstablishment->id,
                'status' => 'Active',
            ]
        );
    }
}
