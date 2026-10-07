<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Backfills the approved establishment type, category, and reporting method for existing establishments.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ReportingMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The mapping approved in Phase 0 (D7), keyed by slug (stable across
     * environments, unlike ids) and guarded by the legacy `category` slug
     * each row is expected to have — a row whose data differs is left
     * alone instead of being silently reclassified. "dahican-surf-guides"
     * is intentionally NOT typed here: its details (surf lessons, board
     * rentals, a fixed location, an active account) do not clearly fit
     * "Tour Guide Service", which would also make it list-only with no QR.
     * It waits for a confirmed type.
     *
     * @var array<string, array{category: string, type: ?string, reporting: string}>
     */
    private const MAPPING = [
        'botanika-nature-resort' => ['category' => 'accommodation', 'type' => 'Resort', 'reporting' => 'DIGITAL'],
        'badjao-seafront' => ['category' => 'restaurants', 'type' => 'Restaurant', 'reporting' => 'DIGITAL'],
        'pasalubong-center' => ['category' => 'local-delicacies', 'type' => 'Other Food & Dining', 'reporting' => 'PAPER_LGU'],
        'dahican-surf-guides' => ['category' => 'tour-guides', 'type' => null, 'reporting' => 'DIGITAL'],
        'delicacies-hub' => ['category' => 'local-delicacies', 'type' => 'Other Food & Dining', 'reporting' => 'PAPER_LGU'],
        'tourist-transport-terminal' => ['category' => 'transportation', 'type' => 'Van / Shuttle Service', 'reporting' => 'PAPER_LGU'],
        'itour-demo-establishment-mati' => ['category' => 'accommodation', 'type' => 'Resort', 'reporting' => 'DIGITAL'],
        'itour-demo-establishment-baganga' => ['category' => 'accommodation', 'type' => 'Resort', 'reporting' => 'PAPER_LGU'],
    ];

    /** Demo rows seeded without a cat_id (RbacDemoAccountSeeder ran after CategorySeeder). */
    private const DEMO_SLUGS_MISSING_CATEGORY = ['itour-demo-establishment-mati', 'itour-demo-establishment-baganga'];

    /**
     * Only fills values that are still empty or still the old DIGITAL
     * default — never overwrites a value someone already set. A row is
     * only switched to Manual/Paper when it has no linked account.
     */
    public function up(): void
    {
        $intAccommodationCategoryId = DB::table('tblcategories')->where('cat_name', 'Accommodation')->value('cat_id');

        DB::transaction(function () use ($intAccommodationCategoryId) {
            foreach (self::MAPPING as $strSlug => $arrTarget) {
                $objRowQuery = fn () => DB::table('listings')->where('slug', $strSlug)->where('category', $arrTarget['category']);

                // Summary comment: establishment type, only where still unset.
                if ($arrTarget['type'] !== null) {
                    $objRowQuery()->whereNull('type')->update(['type' => $arrTarget['type']]);
                }

                // Summary comment: Manual/Paper, only from the old default and only without an account.
                if ($arrTarget['reporting'] === ReportingMethod::ManualPaper->value) {
                    $objRowQuery()
                        ->where('reporting_mode', ReportingMethod::OnlineItour->value)
                        ->whereNotExists(function ($query) {
                            $query->selectRaw('1')->from('users')->whereColumn('users.establishment_id', 'listings.id');
                        })
                        ->update(['reporting_mode' => ReportingMethod::ManualPaper->value]);
                }
            } // end foreach mapping

            // Summary comment: the missing Accommodation category on the two demo rows.
            if ($intAccommodationCategoryId !== null) {
                DB::table('listings')
                    ->whereIn('slug', self::DEMO_SLUGS_MISSING_CATEGORY)
                    ->where('category', 'accommodation')
                    ->whereNull('cat_id')
                    ->update(['cat_id' => $intAccommodationCategoryId]);
            }
        });
    }

    /**
     * Reverts only values that still equal what up() wrote, so later
     * manual corrections are kept.
     */
    public function down(): void
    {
        $intAccommodationCategoryId = DB::table('tblcategories')->where('cat_name', 'Accommodation')->value('cat_id');

        DB::transaction(function () use ($intAccommodationCategoryId) {
            foreach (self::MAPPING as $strSlug => $arrTarget) {
                if ($arrTarget['type'] !== null) {
                    DB::table('listings')->where('slug', $strSlug)->where('type', $arrTarget['type'])->update(['type' => null]);
                }

                if ($arrTarget['reporting'] === ReportingMethod::ManualPaper->value) {
                    DB::table('listings')
                        ->where('slug', $strSlug)
                        ->where('reporting_mode', ReportingMethod::ManualPaper->value)
                        ->update(['reporting_mode' => ReportingMethod::OnlineItour->value]);
                }
            } // end foreach mapping

            if ($intAccommodationCategoryId !== null) {
                DB::table('listings')
                    ->whereIn('slug', self::DEMO_SLUGS_MISSING_CATEGORY)
                    ->where('cat_id', $intAccommodationCategoryId)
                    ->update(['cat_id' => null]);
            }
        });
    }
};
