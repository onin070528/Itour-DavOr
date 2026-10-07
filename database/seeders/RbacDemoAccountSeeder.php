<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Seeds the development RBAC accounts — PTO (primary and secondary), one LGU Tourism Admin per municipality/city, and the demo Establishment.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace Database\Seeders;

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RbacDemoAccountSeeder extends Seeder
{
    /**
     * One official LGU Tourism Admin account per municipality/city, keyed by
     * the existing municipalities.code (see MunicipalitySeeder) — never by a
     * name this seeder would have to guess at. Value: [email local part,
     * office name]. Emails follow tourism.<municipality>@itourdavor.gov.ph.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const LGU_ACCOUNTS = [
        'MATI' => ['mati', 'Mati City Tourism Office'],
        'BAG' => ['baganga', 'Baganga Municipal Tourism Office'],
        'BOS' => ['boston', 'Boston Municipal Tourism Office'],
        'CAT' => ['cateel', 'Cateel Municipal Tourism Office'],
        'CAR' => ['caraga', 'Caraga Municipal Tourism Office'],
        'GOV' => ['govgen', 'Governor Generoso Municipal Tourism Office'],
        'LUP' => ['lupon', 'Lupon Municipal Tourism Office'],
        'MAN' => ['manay', 'Manay Municipal Tourism Office'],
        'BNB' => ['banaybanay', 'Banaybanay Municipal Tourism Office'],
        'SAN' => ['sanisidro', 'San Isidro Municipal Tourism Office'],
        'TAR' => ['tarragona', 'Tarragona Municipal Tourism Office'],
    ];

    private const EMAIL_DOMAIN = 'itourdavor.gov.ph';

    private const SECONDARY_PTO_EMAIL = 'tourism.admin2@itourdavor.gov.ph';

    /**
     * Seeds the RBAC development accounts (see docs/DEV_ACCOUNTS.md),
     * alongside — not instead of — UserSeeder's demo dataset. Refuses to
     * run in production, and refuses to run if SEED_DEMO_PASSWORD isn't
     * set, rather than falling back to a hard-coded password. The password
     * is never printed or logged.
     *
     * Safe to re-run: every account is keyed by email, and every
     * municipality is looked up, never created. Aborts before writing
     * anything if a municipality is missing or already has a different
     * active LGU account.
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

        $arrMunicipalitiesByCode = $this->_resolveMunicipalities();
        $this->_assertNoConflictingLguAccounts($arrMunicipalitiesByCode);

        DB::transaction(function () use ($arrMunicipalitiesByCode, $strPassword) {
            $this->_seedDemoEstablishmentsAndPrimaryAccounts($arrMunicipalitiesByCode['MATI'], $arrMunicipalitiesByCode['BAG'], $strPassword);

            $this->_seedAccountWithTemporaryPassword(self::SECONDARY_PTO_EMAIL, [
                'name' => 'iTOUR PTO Administrator (Secondary)',
                'role' => UserRole::PtoAdministrator,
                'organization_name' => 'Provincial Tourism Office',
                'organization_subtitle' => 'Province of Davao Oriental',
                'municipality_id' => null,
                'establishment_id' => null,
            ], $strPassword);

            foreach (self::LGU_ACCOUNTS as $strCode => [$strEmailLocalPart, $strOfficeName]) {
                $objMunicipality = $arrMunicipalitiesByCode[$strCode];

                // organization_subtitle must be the exact municipality name —
                // it's the display label LGU pages key on.
                $this->_seedAccountWithTemporaryPassword(self::_lguEmailFor($strEmailLocalPart), [
                    'name' => "LGU Tourism Admin ({$objMunicipality->name})",
                    'role' => UserRole::Lgu,
                    'organization_name' => $strOfficeName,
                    'organization_subtitle' => $objMunicipality->name,
                    'municipality_id' => $objMunicipality->id,
                    'establishment_id' => null,
                ], $strPassword);
            } // end foreach LGU account
        });
    }

    /**
     * Every email this seeder owns for an LGU account, keyed by
     * municipality code — shared with the tests so the list lives in
     * one place.
     *
     * @return array<string, string>
     */
    public static function lguEmailsByMunicipalityCode(): array
    {
        return collect(self::LGU_ACCOUNTS)
            ->map(fn (array $arrAccount) => self::_lguEmailFor($arrAccount[0]))
            ->all();
    }

    private static function _lguEmailFor(string $strEmailLocalPart): string
    {
        return 'tourism.'.$strEmailLocalPart.'@'.self::EMAIL_DOMAIN;
    }

    /**
     * Looks up every municipality this seeder needs by its existing code.
     * Never creates one — a missing record means MunicipalitySeeder hasn't
     * run (or the data differs from what this seeder expects), and guessing
     * would risk a duplicate municipality.
     *
     * @return array<string, Municipality>
     */
    private function _resolveMunicipalities(): array
    {
        $arrMunicipalitiesByCode = Municipality::query()
            ->whereIn('code', array_keys(self::LGU_ACCOUNTS))
            ->get()
            ->keyBy('code')
            ->all();

        $arrMissingCodes = array_values(array_diff(array_keys(self::LGU_ACCOUNTS), array_keys($arrMunicipalitiesByCode)));

        if ($arrMissingCodes !== []) {
            throw new RuntimeException('RbacDemoAccountSeeder: aborted — no municipality record for code(s) '.implode(', ', $arrMissingCodes).'. Run MunicipalitySeeder first; this seeder never creates municipalities.');
        }

        return $arrMunicipalitiesByCode;
    }

    /**
     * Only one active LGU account is allowed per municipality
     * (users_one_active_lgu_per_municipality_unique). Fails with a readable
     * message, before anything is written, if some other active LGU
     * account already holds one of these municipalities.
     *
     * @param  array<string, Municipality>  $arrMunicipalitiesByCode
     */
    private function _assertNoConflictingLguAccounts(array $arrMunicipalitiesByCode): void
    {
        $arrConflictingEmails = User::query()
            ->where('role', UserRole::Lgu)
            ->where('status', '!=', 'Inactive')
            ->whereIn('municipality_id', collect($arrMunicipalitiesByCode)->pluck('id'))
            ->whereNotIn('email', self::lguEmailsByMunicipalityCode())
            ->pluck('email')
            ->all();

        if ($arrConflictingEmails !== []) {
            throw new RuntimeException('RbacDemoAccountSeeder: aborted — these active LGU accounts already hold a municipality: '.implode(', ', $arrConflictingEmails).'. Deactivate them first (UserSeeder does this for the legacy demo accounts).');
        }
    }

    /**
     * Creates the account, or refreshes its profile fields if it exists.
     * The temporary password and the must-change-password flag are only
     * (re)applied while the account has never set a password of its own
     * (usr_password_changed_at is null), so re-seeding never undoes a
     * password the person already chose.
     *
     * @param  array<string, mixed>  $arrProfile
     */
    private function _seedAccountWithTemporaryPassword(string $strEmail, array $arrProfile, string $strPassword): void
    {
        $objUser = User::query()->firstOrNew(['email' => $strEmail]);

        $objUser->fill([
            ...$arrProfile,
            'email_verified_at' => $objUser->email_verified_at ?? now(),
            'status' => 'Active',
        ]);

        if ($objUser->usr_password_changed_at === null) {
            $objUser->password = $strPassword;
            $objUser->usr_must_change_password = true;
        }

        $objUser->save();
    }

    /**
     * The original RBAC-foundation fixtures, unchanged: two demo
     * establishments, the primary PTO account, and the demo Establishment
     * account linked to the Mati one.
     */
    private function _seedDemoEstablishmentsAndPrimaryAccounts(Municipality $objMati, Municipality $objBaganga, string $strPassword): void
    {
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
        // see Listing::isPubliclyVisible()). The linked demo Establishment
        // account can still manage its own listing regardless of status,
        // same as any real not-yet-published establishment.
        // Null when categories are not seeded yet; CategorySeeder's legacy-slug
        // backfill then fills it on its next run.
        $intAccommodationCategoryId = Category::query()->where('cat_name', 'Accommodation')->value('cat_id');

        $objMatiEstablishment = Listing::query()->updateOrCreate(
            ['slug' => 'itour-demo-establishment-mati'],
            [
                'name' => 'iTOUR Demo Establishment (Mati)',
                'category' => 'accommodation',
                'municipality' => 'City of Mati',
                'cat_id' => $intAccommodationCategoryId,
                'type' => 'Resort',
                'municipality_id' => $objMati->id,
                'barangay' => 'Poblacion',
                'description' => 'RBAC demo/test fixture — not a real establishment.',
                'status' => 'DRAFT',
                // Has the demo Establishment account below, so it reports online.
                'reporting_mode' => ReportingMethod::OnlineItour,
            ]
        );

        Listing::query()->updateOrCreate(
            ['slug' => 'itour-demo-establishment-baganga'],
            [
                'name' => 'iTOUR Demo Establishment (Baganga)',
                'category' => 'accommodation',
                'municipality' => 'Baganga',
                'cat_id' => $intAccommodationCategoryId,
                'type' => 'Resort',
                'municipality_id' => $objBaganga->id,
                'barangay' => 'Poblacion',
                'description' => 'RBAC demo/test fixture — not a real establishment. Used to verify cross-municipality access denial.',
                'status' => 'DRAFT',
                // No account, so its reports are encoded by the Baganga LGU.
                'reporting_mode' => ReportingMethod::ManualPaper,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'tourism@itourdavor.gov.ph'],
            [
                'name' => 'iTOUR PTO Demo Account',
                'password' => $strPassword,
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
            ['email' => 'establishments@itourdavor.gov.ph'],
            [
                'name' => 'iTOUR Establishment Demo Account',
                'password' => $strPassword,
                'email_verified_at' => now(),
                'role' => UserRole::Establishment,
                'organization_name' => $objMatiEstablishment->name,
                'organization_subtitle' => "{$objMatiEstablishment->barangay}, {$objMatiEstablishment->municipality}",
                'municipality_id' => $objMati->id,
                'establishment_id' => $objMatiEstablishment->id,
                'status' => 'Active',
            ]
        );
    }
}
