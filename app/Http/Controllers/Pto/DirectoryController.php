<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide Tourism Directory — destinations, establishments,
 * and Travel & Tours guides in one category-driven list/map view.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\ManagingLevel;
use App\Http\Requests\SaveAttractionRequest;
use App\Http\Requests\SaveEstablishmentRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Services\ListingPublishWorkflow;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DirectoryController extends PtoController
{
    /**
     * One category-driven list/map view replacing the old separate
     * Destinations / Establishments / Map pages. Every filter (the
     * Categories panel, the list columns, the map pins) reads from
     * tbl_categories and Listing::isQrEnabled() — adding or renaming a
     * category needs no code change here.
     */
    public function index(Request $objRequest): View
    {
        $objCategories = Category::query()->orderBy('cat_sort_order')->get();

        $objListings = Listing::query()
            ->with(['categoryRecord', 'establishmentUser', 'establishmentImages' => fn ($objQuery) => $objQuery->where('img_status', 'PUBLISHED')])
            ->orderBy('lst_name')
            ->get();

        $intCategoryCounts = $objListings->countBy(fn (Listing $objListing) => $objListing->cat_id);

        return $this->renderPto($objRequest, 'pto.directory.index', 'directory', 'Tourism Directory', [
            'listings' => $objListings,
            'categories' => $objCategories,
            'categoryCounts' => $intCategoryCounts,
            'municipalities' => Municipality::query()->orderBy('mun_name')->get(),
        ]);
    }

    /**
     * A new directory record starts as Draft (Objective 3, D3) — nothing
     * the PTO creates is published automatically. It goes live only through
     * the review workflow: a PTO-managed destination is moved to Pending
     * Review with submitForReview() and then approved (publish()); an
     * LGU-managed destination or an establishment is submitted by its LGU.
     * Every step is audit-logged.
     */
    public function store(Request $objRequest): RedirectResponse
    {
        $arrFields = $this->validatedListingFields($objRequest);
        $intMunicipalityId = $this->municipalityIdByName($arrFields['lst_municipality']);
        $strCategory = $this->legacyCategorySlug($arrFields['cat_id']);
        $objManagingLevel = $this->_validatedManagingLevel($objRequest, $strCategory);
        $objPto = $objRequest->user();

        try {
            $objListing = Listing::query()->make([
                ...$arrFields,
                'lst_slug' => Listing::uniqueSlug($arrFields['lst_name'], 'listing'),
                'lst_category' => $strCategory,
                'mun_id' => $intMunicipalityId,
                'lst_status' => 'DRAFT',
            ]);
            // Set by application logic only — never mass-assigned from the request.
            $objListing->lst_managing_level = $objManagingLevel;
            $objListing->lst_created_by = $objPto->usr_id;
            $objListing->lst_updated_by = $objPto->usr_id;
            $objListing->save();
        } catch (\Throwable $objException) {
            Log::error('Failed to create PTO directory listing.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($objPto, $objListing->auditEntityType(), $objListing->lst_id, $intMunicipalityId, null, [
            'name' => $objListing->lst_name,
            'category' => $objListing->categoryRecord?->cat_name,
            'municipality' => $objListing->lst_municipality,
            'status' => 'DRAFT',
        ]);

        return back()->with('toast', "{$objListing->lst_name} was added as a Draft. It is not public until it passes review.");
    }

    public function update(Request $objRequest, Listing $listing): RedirectResponse
    {
        $arrFields = $this->validatedListingFields($objRequest);
        $intMunicipalityId = $this->municipalityIdByName($arrFields['lst_municipality']);
        $strCategory = $this->legacyCategorySlug($arrFields['cat_id']);
        $objManagingLevel = $this->_validatedManagingLevel($objRequest, $strCategory);
        $arrBefore = $listing->getOriginal();

        try {
            $listing->fill([
                ...$arrFields,
                'lst_category' => $strCategory,
                'mun_id' => $intMunicipalityId,
            ]);
            $listing->lst_managing_level = $objManagingLevel;
            $listing->lst_updated_by = $objRequest->user()->usr_id;
            $listing->save();
        } catch (\Throwable $objException) {
            Log::error('Failed to update PTO directory listing.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        // A4/A3: the PTO can edit LGU-owned records; every such edit is
        // audit-logged with the old and new values via OperationLogger.
        OperationLogger::updated($objRequest->user(), $listing->auditEntityType(), $listing->lst_id, $intMunicipalityId, $listing->lst_id, OperationLogger::diff($arrBefore, $listing));

        return back()->with('toast', 'Listing saved.');
    }

    /**
     * "Change Status", always with a reason written into the operation log:
     * suspend or archive a record, restore an archived destination to Draft,
     * or reinstate a suspended destination to Draft (Objective 3, D3). The
     * allowed targets depend on the record's current status
     * (Listing::statusChangeOptions()) — a destination is never set straight
     * back to Published here. Publishing is only ever
     * App\Services\ListingPublishWorkflow::publish(), gated by
     * App\Policies\ListingPolicy::publish() (P1 — only the PTO publishes).
     */
    public function updateStatus(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('archive', $listing), 403);

        $arrData = $objRequest->validate([
            'status' => ['required', 'string', Rule::in($listing->statusChangeOptions())],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'status.in' => 'That status change is not allowed from the listing\'s current status.',
        ]);

        try {
            $objWorkflow->changeStatus($objRequest->user(), $listing, $arrData['status'], $arrData['reason']);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        $strToast = $arrData['status'] === 'DRAFT'
            ? "{$listing->lst_name} is back in Draft. It must be submitted and approved again before it is public."
            : "{$listing->lst_name} is now {$arrData['status']}.";

        return back()->with('toast', $strToast);
    }

    /**
     * "Submit for review": Draft (or Returned for Correction) -> Pending
     * Review for a destination the PTO manages (Objective 3, D10) — the
     * same step an LGU takes for its own destinations
     * (Lgu\DirectoryController::submitToPto()). Never publishes; the PTO
     * then decides with "Approve & Publish" on the review screen.
     */
    public function submitForReview(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('submit', $listing), 403);

        try {
            $objWorkflow->submitToPto($objRequest->user(), $listing);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} is now Pending Review. Approve & Publish it from the review screen when it is ready.");
    }

    /**
     * "Approve & Publish": FOR_PTO_REVIEW → PUBLISHED, or held changes to a
     * Published listing applied to the live listing, in one transaction.
     * P1: only the PTO may do this — see App\Policies\ListingPolicy::publish().
     */
    public function publish(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('publish', $listing), 403);

        $blnIsChangeRequest = $listing->hasPendingChanges();

        try {
            $objWorkflow->publish($objRequest->user(), $listing);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', $blnIsChangeRequest ? "The changes to {$listing->lst_name} are now live." : "{$listing->lst_name} is now live.");
    }

    /**
     * "Return for Correction" (remarks required): FOR_PTO_REVIEW →
     * FOR_CORRECTION, or held changes to a Published listing returned while
     * the published version stays live. Every LGU user in that
     * municipality is notified and sees the remarks.
     */
    public function returnToLgu(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('publish', $listing), 403);

        $arrData = $objRequest->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $objWorkflow->returnToLgu($objRequest->user(), $listing, $arrData['reason']);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} was returned to the LGU for correction.");
    }

    /**
     * PUBLISHED → UNPUBLISHED, with a reason — pulls a live listing back
     * for revision (distinct from Suspend/Archive, see updateStatus()).
     */
    public function unpublish(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('publish', $listing), 403);

        $arrData = $objRequest->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $objWorkflow->unpublish($objRequest->user(), $listing, $arrData['reason']);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} was unpublished.");
    }

    /**
     * Category/type-dependent validation: coordinates are never accepted
     * for a Tour Guide (so it can never collect a QR identifier either — see
     * Listing::isQrEnabled()), a note is required for the "Others" category,
     * and a license number is required for a Tour Guide.
     *
     * @return array<string, mixed>
     */
    private function validatedListingFields(Request $objRequest): array
    {
        $objCategory = Category::query()->find($objRequest->input('cat_id'));
        $blnIsGuide = Listing::isTourGuideType($objRequest->input('type'));
        $blnIsDestination = $objCategory?->isDestinationCategory() ?? false;

        // Summary comment: destination-only fields (Objective 3) are read
        // only for the Tourist Destinations category, under their own names
        // so the hidden establishment Type select can never collide with them.
        $arrDestinationRules = $blnIsDestination
            ? ['destination_type' => SaveAttractionRequest::destinationTypeRules(), ...SaveAttractionRequest::visitorFieldRules()]
            : [];

        // Summary comment: the establishment detail rules (including R13's
        // Category -> Type check) are shared with the LGU form; PTO adds the
        // unrestricted category and its municipality picker.
        $arrData = $objRequest->validate([
            ...SaveEstablishmentRequest::listingFieldRules($objCategory, $blnIsGuide),
            ...$arrDestinationRules,
            'cat_id' => ['required', 'integer', 'exists:tbl_categories,cat_id'],
            'municipality' => ['required', 'string', Rule::in(Municipality::query()->pluck('mun_name'))],
        ]);

        $arrDestinationFields = $blnIsDestination
            ? ['lst_visitor_information' => $arrData['visitor_information'] ?? null, 'lst_entrance_fee' => $arrData['entrance_fee'] ?? null]
            : [];

        return [
            ...$arrDestinationFields,
            'lst_name' => $arrData['name'],
            'cat_id' => $arrData['cat_id'],
            'lst_type' => $blnIsDestination ? ($arrData['destination_type'] ?? null) : ($arrData['type'] ?? null),
            'lst_owner_name' => $arrData['owner_name'] ?? null,
            'lst_municipality' => $arrData['municipality'],
            'lst_barangay' => $arrData['barangay'] ?? null,
            'lst_lat' => $arrData['lat'] ?? null,
            'lst_lng' => $arrData['lng'] ?? null,
            'lst_description' => $arrData['description'] ?? null,
            'lst_contact_office' => $arrData['contact_office'] ?? null,
            'lst_contact_phone' => $arrData['contact_phone'] ?? null,
            'lst_email' => $arrData['email'] ?? null,
            'lst_website' => $arrData['website'] ?? null,
            'lst_hours' => $arrData['hours'] ?? null,
            'lst_license_number' => $arrData['license_number'] ?? null,
            'lst_accreditation_status' => $arrData['accreditation_status'] ?? null,
            'lst_category_note' => $arrData['category_note'] ?? null,
        ];
    }

    /**
     * The managing level chosen for a destination-only record (LGU or PTO,
     * D10). Left empty it stays null, which reads as LGU-managed — the same
     * as every destination created before managing levels existed. Always
     * null for an establishment, which has no managing level.
     */
    private function _validatedManagingLevel(Request $objRequest, string $strCategory): ?ManagingLevel
    {
        if ($strCategory !== 'destinations') {
            return null;
        }

        $arrData = $objRequest->validate([
            'managing_level' => ['nullable', Rule::enum(ManagingLevel::class)],
        ]);

        return isset($arrData['managing_level']) ? ManagingLevel::from($arrData['managing_level']) : null;
    }

    private function municipalityIdByName(string $strMunicipality): ?int
    {
        return Municipality::query()->where('mun_name', $strMunicipality)->value('mun_id');
    }

    /**
     * Keeps the legacy free-text `lst_category` column in sync with the new
     * `cat_id` relation (still read by App\Support\TourismCatalog and the
     * public Explore page until those callers move onto the relation).
     */
    private function legacyCategorySlug(int $intCategoryId): string
    {
        return Category::query()->find($intCategoryId)?->legacySlug() ?? 'others';
    }
}
