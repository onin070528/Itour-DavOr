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
     * FK is already set) backfill of the RBAC-facing municipality_id /
     * establishment_id FKs from the pre-existing free-text columns
     * (listings.municipality, users.organization_subtitle,
     * users.organization_name). Always runs, in every environment — this
     * is structural data consistency, not demo data. Must run after
     * MunicipalitySeeder, UserSeeder, and ListingSeeder.
     */
    public function run(): void
    {
        $municipalitiesByName = Municipality::query()->get()->keyBy('name');

        Listing::query()->whereNull('municipality_id')->each(function (Listing $listing) use ($municipalitiesByName) {
            $municipality = $municipalitiesByName->get($listing->municipality);

            if ($municipality) {
                $listing->update(['municipality_id' => $municipality->id]);
            } else {
                Log::warning('RbacScopeBackfillSeeder: no municipality match for listing.', [
                    'listing_id' => $listing->id,
                    'municipality' => $listing->municipality,
                ]);
            }
        });

        // Establishment users' establishment_id first — their municipality_id
        // is then derived from that listing (see below), since
        // organization_subtitle for these accounts is a "{barangay}, {municipality}"
        // compound string, not an exact municipality name, and won't match
        // $municipalitiesByName directly.
        $this->backfillEstablishmentIds();

        User::query()->whereNull('municipality_id')->whereNotNull('organization_subtitle')->each(function (User $user) use ($municipalitiesByName) {
            $municipality = $municipalitiesByName->get($user->organization_subtitle);

            if ($municipality) {
                $user->update(['municipality_id' => $municipality->id]);

                return;
            }

            // Establishment accounts: derive municipality from the linked
            // listing rather than parsing the "{barangay}, {municipality}"
            // display string.
            if ($user->role === UserRole::Establishment && $user->establishment_id) {
                $listingMunicipalityId = Listing::query()->whereKey($user->establishment_id)->value('municipality_id');

                if ($listingMunicipalityId) {
                    $user->update(['municipality_id' => $listingMunicipalityId]);

                    return;
                }
            }

            Log::warning('RbacScopeBackfillSeeder: no municipality match for user.', [
                'user_id' => $user->id,
                'organization_subtitle' => $user->organization_subtitle,
            ]);
        });
    }

    /**
     * Only backfilled when the organization_name → Listing.name match is
     * unambiguous (exactly one hit) and that listing isn't already linked
     * to a different user — the same "don't guess wrong" caution that
     * motivated moving establishment resolution off of name-matching in
     * the first place (see Establishment\ProfileController).
     */
    private function backfillEstablishmentIds(): void
    {
        User::query()
            ->where('role', UserRole::Establishment)
            ->whereNull('establishment_id')
            ->whereNotNull('organization_name')
            ->each(function (User $user) {
                $matches = Listing::query()
                    ->where('name', $user->organization_name)
                    ->where('category', '!=', 'destinations')
                    ->get();

                if ($matches->count() !== 1) {
                    Log::warning('RbacScopeBackfillSeeder: skipped establishment_id backfill (ambiguous or no match).', [
                        'user_id' => $user->id,
                        'organization_name' => $user->organization_name,
                        'match_count' => $matches->count(),
                    ]);

                    return;
                }

                $listing = $matches->first();

                $alreadyLinked = User::query()
                    ->where('establishment_id', $listing->id)
                    ->whereKeyNot($user->id)
                    ->exists();

                if ($alreadyLinked) {
                    Log::warning('RbacScopeBackfillSeeder: skipped establishment_id backfill (listing already linked to another account).', [
                        'user_id' => $user->id,
                        'listing_id' => $listing->id,
                    ]);

                    return;
                }

                $user->update(['establishment_id' => $listing->id]);
            });
    }
}
