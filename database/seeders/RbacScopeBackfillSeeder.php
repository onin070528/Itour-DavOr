<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class RbacScopeBackfillSeeder extends Seeder
{
    /**
     * One-time (but safely re-runnable — every write is a no-op once the
     * FK is already set) backfill of the RBAC-facing mun_id /
     * lst_id FKs from the pre-existing free-text columns
     * (listings.municipality, users.usr_organization_subtitle,
     * users.usr_organization_name). Always runs, in every environment — this
     * is structural data consistency, not demo data. Must run after
     * MunicipalitySeeder, UserSeeder, and ListingSeeder.
     */
    public function run(): void
    {
        $municipalitiesByName = Municipality::query()->get()->keyBy('mun_name');

        Listing::query()->whereNull('mun_id')->each(function (Listing $listing) use ($municipalitiesByName) {
            $municipality = $municipalitiesByName->get($listing->lst_municipality);

            if ($municipality) {
                $listing->update(['mun_id' => $municipality->mun_id]);
            } else {
                Log::warning('RbacScopeBackfillSeeder: no municipality match for listing.', [
                    'lst_id' => $listing->lst_id,
                    'municipality' => $listing->lst_municipality,
                ]);
            }
        });

        // Establishment users' lst_id first — their mun_id
        // is then derived from that listing (see below), since
        // usr_organization_subtitle for these accounts is a "{barangay}, {municipality}"
        // compound string, not an exact municipality name, and won't match
        // $municipalitiesByName directly.
        $this->backfillEstablishmentIds();

        User::query()->whereNull('mun_id')->whereNotNull('usr_organization_subtitle')->each(function (User $user) use ($municipalitiesByName) {
            $municipality = $municipalitiesByName->get($user->usr_organization_subtitle);

            if ($municipality) {
                $user->update(['mun_id' => $municipality->mun_id]);

                return;
            }

            // Establishment accounts: derive municipality from the linked
            // listing rather than parsing the "{barangay}, {municipality}"
            // display string.
            if ($user->usr_role === UserRole::Establishment && $user->lst_id) {
                $listingMunicipalityId = Listing::query()->whereKey($user->lst_id)->value('mun_id');

                if ($listingMunicipalityId) {
                    $user->update(['mun_id' => $listingMunicipalityId]);

                    return;
                }
            }

            Log::warning('RbacScopeBackfillSeeder: no municipality match for user.', [
                'usr_id' => $user->usr_id,
                'usr_organization_subtitle' => $user->usr_organization_subtitle,
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
            ->each(function (User $user) {
                $matches = Listing::query()
                    ->where('lst_name', $user->usr_organization_name)
                    ->where('lst_category', '!=', 'destinations')
                    ->get();

                if ($matches->count() !== 1) {
                    Log::warning('RbacScopeBackfillSeeder: skipped lst_id backfill (ambiguous or no match).', [
                        'usr_id' => $user->usr_id,
                        'usr_organization_name' => $user->usr_organization_name,
                        'match_count' => $matches->count(),
                    ]);

                    return;
                }

                $listing = $matches->first();

                $alreadyLinked = User::query()
                    ->where('lst_id', $listing->lst_id)
                    ->whereKeyNot($user->usr_id)
                    ->exists();

                if ($alreadyLinked) {
                    Log::warning('RbacScopeBackfillSeeder: skipped lst_id backfill (listing already linked to another account).', [
                        'usr_id' => $user->usr_id,
                        'lst_id' => $listing->lst_id,
                    ]);

                    return;
                }

                $user->update(['lst_id' => $listing->lst_id]);
            });
    }
}
