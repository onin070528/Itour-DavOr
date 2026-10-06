<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Backfills the municipality and establishment foreign keys from the older free-text
 * columns.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class RbacScopeBackfillSeeder extends Seeder
{
    /**
     * One-time (but safely re-runnable — every write is a no-op once the
     * FK is already set) backfill of the RBAC-facing mun_id /
     * lst_id FKs from the pre-existing free-text columns
     * (tbl_listings.lst_municipality, tbl_users.usr_organization_subtitle,
     * tbl_users.usr_organization_name, tbl_municipal_reports.mrp_municipality).
     * Always runs, in every environment — this
     * is structural data consistency, not demo data. Must run after
     * MunicipalitySeeder, UserSeeder, ListingSeeder, and MunicipalReportSeeder.
     */
    public function run(): void
    {
        $objMunicipalitiesByName = Municipality::query()->get()->keyBy('mun_name');

        Listing::query()->whereNull('mun_id')->each(function (Listing $objListing) use ($objMunicipalitiesByName) {
            $objMunicipality = $objMunicipalitiesByName->get($objListing->lst_municipality);

            if ($objMunicipality) {
                $objListing->update(['mun_id' => $objMunicipality->mun_id]);
            } else {
                Log::warning('RbacScopeBackfillSeeder: no municipality match for listing.', [
                    'lst_id' => $objListing->lst_id,
                    'municipality' => $objListing->lst_municipality,
                ]);
            }
        });

        // Establishment users' lst_id first — their mun_id
        // is then derived from that listing (see below), since
        // usr_organization_subtitle for these accounts is a "{barangay}, {municipality}"
        // compound string, not an exact municipality name, and won't match
        // $objMunicipalitiesByName directly.
        $this->backfillEstablishmentIds();

        User::query()->whereNull('mun_id')->whereNotNull('usr_organization_subtitle')->each(function (User $objUser) use ($objMunicipalitiesByName) {
            $objMunicipality = $objMunicipalitiesByName->get($objUser->usr_organization_subtitle);

            if ($objMunicipality) {
                $objUser->update(['mun_id' => $objMunicipality->mun_id]);

                return;
            }

            // Establishment accounts: derive municipality from the linked
            // listing rather than parsing the "{barangay}, {municipality}"
            // display string.
            if ($objUser->usr_role === UserRole::Establishment && $objUser->lst_id) {
                $intListingMunicipalityId = Listing::query()->whereKey($objUser->lst_id)->value('mun_id');

                if ($intListingMunicipalityId) {
                    $objUser->update(['mun_id' => $intListingMunicipalityId]);

                    return;
                }
            }

            Log::warning('RbacScopeBackfillSeeder: no municipality match for user.', [
                'usr_id' => $objUser->usr_id,
                'usr_organization_subtitle' => $objUser->usr_organization_subtitle,
            ]);
        });

        MunicipalReport::query()->whereNull('mun_id')->each(function (MunicipalReport $objReport) use ($objMunicipalitiesByName) {
            $objMunicipality = $objMunicipalitiesByName->get($objReport->mrp_municipality);

            if ($objMunicipality) {
                $objReport->update(['mun_id' => $objMunicipality->mun_id]);

                return;
            }

            Log::warning('RbacScopeBackfillSeeder: no municipality match for municipal report.', [
                'mrp_id' => $objReport->mrp_id,
                'mrp_municipality' => $objReport->mrp_municipality,
            ]);
        });
    }

    /**
     * Only backfilled when the usr_organization_name → Listing.name match is
     * unambiguous (exactly one hit) and that listing isn't already linked
     * to a different user — the same "don't guess wrong" caution that
     * motivated moving establishment resolution off of name-matching in
     * the first place (see Establishment\ProfileController).
     */
    private function backfillEstablishmentIds(): void
    {
        User::query()
            ->where('usr_role', UserRole::Establishment)
            ->whereNull('lst_id')
            ->whereNotNull('usr_organization_name')
            ->each(function (User $objUser) {
                $objMatches = Listing::query()
                    ->where('lst_name', $objUser->usr_organization_name)
                    ->where('lst_category', '!=', 'destinations')
                    ->get();

                if ($objMatches->count() !== 1) {
                    Log::warning('RbacScopeBackfillSeeder: skipped lst_id backfill (ambiguous or no match).', [
                        'usr_id' => $objUser->usr_id,
                        'usr_organization_name' => $objUser->usr_organization_name,
                        'match_count' => $objMatches->count(),
                    ]);

                    return;
                }

                $objListing = $objMatches->first();

                $blnAlreadyLinked = User::query()
                    ->where('lst_id', $objListing->lst_id)
                    ->whereKeyNot($objUser->usr_id)
                    ->exists();

                if ($blnAlreadyLinked) {
                    Log::warning('RbacScopeBackfillSeeder: skipped lst_id backfill (listing already linked to another account).', [
                        'usr_id' => $objUser->usr_id,
                        'lst_id' => $objListing->lst_id,
                    ]);

                    return;
                }

                $objUser->update(['lst_id' => $objListing->lst_id]);
            });
    }
}
