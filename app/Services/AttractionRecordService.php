<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Creates and edits destination-only records (tourist attractions) for the LGU — never live without PTO approval.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Services;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Support\OperationLogger;
use Illuminate\Support\Facades\DB;

/**
 * A destination-only record (R9, e.g. Aliwagwag Falls) is a listings row
 * with category `destinations` — the existing destination table, no new
 * one. It has no establishment account, no QR, and no reporting method.
 * The LGU creates it as Not Requested (DRAFT) in its own municipality; it
 * becomes public only through the PTO review in ListingPublishWorkflow
 * (R10). Shared by Lgu\AttractionsController and the older
 * Lgu\DirectoryController destination page, so neither can bypass review.
 */
class AttractionRecordService
{
    /**
     * Every field the LGU may set on an attraction, in form order.
     *
     * @var array<int, string>
     */
    public const FIELDS = ['name', 'barangay', 'lat', 'lng', 'description', 'contact_office', 'contact_phone', 'hours', 'website'];

    public function __construct(private readonly ListingPublishWorkflow $objWorkflow) {}

    /**
     * New attraction: municipality from the LGU's own account (never the
     * request), Not Requested, under the Tourist Destinations category.
     *
     * @param  array<string, mixed>  $arrFields
     */
    public function create(User $objLgu, array $arrFields): Listing
    {
        $objListing = DB::transaction(function () use ($arrFields, $objLgu): Listing {
            $objNewListing = Listing::query()->make([
                ...array_intersect_key($arrFields, array_flip(self::FIELDS)),
                'barangay' => $arrFields['barangay'] ?? '',
                'slug' => Listing::uniqueSlug((string) $arrFields['name']),
                'category' => 'destinations',
                'cat_id' => Category::query()->where('cat_name', Category::DESTINATION_CATEGORY_NAME)->value('cat_id'),
                'municipality' => $objLgu->organization_subtitle,
                'municipality_id' => $objLgu->municipality_id,
                'status' => 'DRAFT',
            ]);
            // Destination-only records do not participate in reporting.
            $objNewListing->reporting_mode = null;
            $objNewListing->save();

            return $objNewListing;
        });

        OperationLogger::created($objLgu, 'destination', $objListing->id, $objListing->municipality_id, null, [
            'name' => $objListing->name,
            'barangay' => $objListing->barangay,
            'municipality' => $objListing->municipality,
            'status' => 'DRAFT',
        ]);

        return $objListing;
    } // end create

    /**
     * Saves an edit. Public destination content is locked while a request
     * is with the PTO, and held for PTO review on a live attraction (the
     * published version stays public); every other field saves directly.
     * Returns true when changes were sent to the PTO for review.
     *
     * @param  array<string, mixed>  $arrFields  Only the keys present are changed.
     */
    public function update(User $objLgu, Listing $objListing, array $arrFields): bool
    {
        $arrFields = array_intersect_key($arrFields, array_flip(self::FIELDS));

        if (array_key_exists('barangay', $arrFields)) {
            $arrFields['barangay'] ??= '';
        }

        $arrPublicFields = array_intersect_key($arrFields, array_flip(Listing::PUBLIC_CONTENT_FIELDS));
        $blnIsLive = $objListing->isPubliclyVisible();
        $arrProposed = $blnIsLive ? $objListing->publicFieldChanges($arrPublicFields) : [];

        // Summary comment: public content is never applied directly while with the PTO or live.
        if ($objListing->hasLockedPublicContent() || $blnIsLive) {
            $arrFields = array_diff_key($arrFields, $arrPublicFields);
        }

        $arrBefore = $objListing->getOriginal();
        $objListing->update($arrFields);

        OperationLogger::updated($objLgu, 'destination', $objListing->id, $objListing->municipality_id, null, OperationLogger::diff($arrBefore, $objListing));

        if ($blnIsLive) {
            $this->objWorkflow->submitPendingChanges($objLgu, $objListing, $arrProposed);
        }

        return $arrProposed !== [];
    } // end update
}
