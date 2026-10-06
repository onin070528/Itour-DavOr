<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the PTO, LGU and Establishment demo accounts and their demo establishments.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

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

        $strPassword = env('SEED_DEMO_PASSWORD');

        if (! $strPassword) {
            $this->command?->warn('RbacDemoAccountSeeder: skipped — SEED_DEMO_PASSWORD is not set in .env.');

            return;
        }

        $objMati = Municipality::query()->where('mun_code', 'MATI')->first();
        $objBaganga = Municipality::query()->where('mun_code', 'BAG')->first();

        if (! $objMati || ! $objBaganga) {
            $this->command?->warn('RbacDemoAccountSeeder: skipped — run MunicipalitySeeder first.');

            return;
        }

        // Fresh, minimal demo establishments — deliberately separate from
        // the existing TourismCatalog-seeded listings (Botanika Nature
        // Resort etc.) so this seeder never collides with ListingSeeder's
        // data. Baganga has zero seeded listings today, so its demo
        // establishment also doubles as the cross-municipality-denial test
        // fixture used by tests/Feature/Rbac/MunicipalityScopingTest.php.
        //
        // status stays 'DRAFT' (not 'PUBLISHED') so these no-image,
        // no-rating placeholders never surface in public-facing curation
        // (TourismCatalog::featuredEstablishments()/signatureExperiences(),
        // the /explore hub — both filter to publicly-visible listings only,
        // see Listing::isPubliclyVisible()). The linked demo
        // Establishment account can still manage its own listing regardless
        // of status, same as any real not-yet-published establishment.
        $objMatiEstablishment = Listing::query()->updateOrCreate(
            ['lst_slug' => 'itour-demo-establishment-mati'],
            [
                'lst_name' => 'iTOUR Demo Establishment (Mati)',
                'lst_category' => 'accommodation',
                'lst_municipality' => 'City of Mati',
                'mun_id' => $objMati->mun_id,
                'lst_barangay' => 'Poblacion',
                'lst_description' => 'RBAC demo/test fixture — not a real establishment.',
                'lst_status' => 'DRAFT',
            ]
        );

        Listing::query()->updateOrCreate(
            ['lst_slug' => 'itour-demo-establishment-baganga'],
            [
                'lst_name' => 'iTOUR Demo Establishment (Baganga)',
                'lst_category' => 'accommodation',
                'lst_municipality' => 'Baganga',
                'mun_id' => $objBaganga->mun_id,
                'lst_barangay' => 'Poblacion',
                'lst_description' => 'RBAC demo/test fixture — not a real establishment. Used to verify cross-municipality access denial.',
                'lst_status' => 'DRAFT',
            ]
        );

        User::query()->updateOrCreate(
            ['usr_email' => 'tourism@itourdavor.gov.ph'],
            [
                'usr_name' => 'iTOUR PTO Demo Account',
                'usr_password' => $strPassword,
                'usr_email_verified_at' => now(),
                'usr_role' => UserRole::PtoAdministrator,
                'usr_organization_name' => 'Provincial Tourism Office',
                'usr_organization_subtitle' => 'Province of Davao Oriental',
                'mun_id' => null,
                'lst_id' => null,
                'usr_status' => 'Active',
            ]
        );

        User::query()->updateOrCreate(
            ['usr_email' => 'tourism.mati@itourdavor.gov.ph'],
            [
                'usr_name' => 'iTOUR LGU Demo Account (Mati)',
                'usr_password' => $strPassword,
                'usr_email_verified_at' => now(),
                'usr_role' => UserRole::Lgu,
                'usr_organization_name' => 'Mati City Tourism Office',
                'usr_organization_subtitle' => 'City of Mati',
                'mun_id' => $objMati->mun_id,
                'lst_id' => null,
                'usr_status' => 'Active',
            ]
        );

        User::query()->updateOrCreate(
            ['usr_email' => 'establishments@itourdavor.gov.ph'],
            [
                'usr_name' => 'iTOUR Establishment Demo Account',
                'usr_password' => $strPassword,
                'usr_email_verified_at' => now(),
                'usr_role' => UserRole::Establishment,
                'usr_organization_name' => $objMatiEstablishment->lst_name,
                'usr_organization_subtitle' => "{$objMatiEstablishment->lst_barangay}, {$objMatiEstablishment->lst_municipality}",
                'mun_id' => $objMati->mun_id,
                'lst_id' => $objMatiEstablishment->lst_id,
                'usr_status' => 'Active',
            ]
        );
    }
}
