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

use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Services\ListingPublishWorkflow;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            ->with(['categoryRecord', 'establishmentImages' => fn ($objQuery) => $objQuery->where('img_status', 'PUBLISHED')])
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

    public function store(Request $objRequest): RedirectResponse
    {
        $arrFields = $this->validatedListingFields($objRequest);
        $intMunicipalityId = $this->municipalityIdByName($arrFields['lst_municipality']);
        $strCategory = $this->legacyCategorySlug($arrFields['cat_id']);

        try {
            $objListing = Listing::query()->create([
                ...$arrFields,
                'lst_slug' => $this->uniqueListingSlug($arrFields['lst_name']),
                'lst_category' => $strCategory,
                'mun_id' => $intMunicipalityId,
                // The PTO is the final authority in the publish workflow —
                // an establishment it creates itself needs no review queue
                // of its own, so it's PUBLISHED immediately. Destinations
                // keep the unchanged Active/Suspended/Archived vocabulary.
                'lst_status' => $strCategory === 'destinations' ? 'Active' : 'PUBLISHED',
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to create PTO directory listing.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($objRequest->user(), 'establishment', $objListing->lst_id, $intMunicipalityId, null, [
            'name' => $objListing->lst_name,
            'category' => $objListing->categoryRecord?->cat_name,
            'municipality' => $objListing->lst_municipality,
        ]);

        return back()->with('toast', "{$objListing->lst_name} was added.");
    }

    public function update(Request $objRequest, Listing $listing): RedirectResponse
    {
        $arrFields = $this->validatedListingFields($objRequest);
        $intMunicipalityId = $this->municipalityIdByName($arrFields['lst_municipality']);
        $arrBefore = $listing->getOriginal();

        try {
            $listing->update([
                ...$arrFields,
                'lst_category' => $this->legacyCategorySlug($arrFields['cat_id']),
                'mun_id' => $intMunicipalityId,
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update PTO directory listing.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        // A4/A3: the PTO can edit LGU-owned records; every such edit is
        // audit-logged with the old and new values via OperationLogger.
        OperationLogger::updated($objRequest->user(), 'establishment', $listing->lst_id, $intMunicipalityId, $listing->lst_id, OperationLogger::diff($arrBefore, $listing));

        return back()->with('toast', 'Listing saved.');
    }

    /**
     * Activate/suspend/archive a destination, or suspend/archive an
     * establishment — always requires a reason, written into the operation
     * log alongside the old/new status. An establishment's PUBLISHED/
     * UNPUBLISHED state is never set here: that's App\Services\
     * ListingPublishWorkflow's job (publish()/unpublish()/
     * returnToLgu()), each gated by App\Policies\ListingPolicy::publish()
     * (P1 — only the PTO publishes).
     */
    public function updateStatus(Request $objRequest, Listing $listing): RedirectResponse
    {
        $arrAllowedStatuses = $listing->lst_category === 'destinations'
            ? ['Active', 'Suspended', 'Archived']
            : ['Suspended', 'Archived'];

        $arrData = $objRequest->validate([
            'status' => ['required', 'string', Rule::in($arrAllowedStatuses)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $arrBefore = $listing->getOriginal();

        try {
            $listing->update(['lst_status' => $arrData['status']]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update PTO directory listing status.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated(
            $objRequest->user(),
            'establishment',
            $listing->lst_id,
            $listing->mun_id,
            $listing->lst_id,
            OperationLogger::diff($arrBefore, $listing),
            $arrData['reason'],
        );

        return back()->with('toast', "{$listing->lst_name} is now {$arrData['status']}.");
    }

    /**
     * FOR_PTO_REVIEW → PUBLISHED, in one transaction. P1: only the PTO may
     * do this — see App\Policies\ListingPolicy::publish().
     */
    public function publish(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('publish', $listing), 403);

        try {
            $objWorkflow->publish($objRequest->user(), $listing);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} is now live.");
    }

    /**
     * FOR_PTO_REVIEW → DRAFT, with a reason every LGU user in that
     * municipality sees.
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

        return back()->with('toast', "{$listing->lst_name} was returned to the LGU.");
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
        $blnIsGuide = $objRequest->input('type') === 'Tour Guide';

        $arrData = $objRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'cat_id' => ['required', 'integer', 'exists:tbl_categories,cat_id'],
            'type' => ['nullable', 'string', Rule::in(['Tour Operator', 'Tour Guide'])],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'municipality' => ['required', 'string', Rule::in(Municipality::query()->pluck('mun_name'))],
            'barangay' => [$blnIsGuide ? 'nullable' : 'required', 'string', 'max:255'],
            'lat' => [$blnIsGuide ? 'prohibited' : 'nullable', 'numeric', 'between:-90,90'],
            'lng' => [$blnIsGuide ? 'prohibited' : 'nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'contact_office' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'license_number' => [$blnIsGuide ? 'required' : 'nullable', 'string', 'max:255'],
            'accreditation_status' => ['nullable', 'string', 'max:255'],
            'category_note' => [$objCategory?->isOthers() ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        return [
            'lst_name' => $arrData['name'],
            'cat_id' => $arrData['cat_id'],
            'lst_type' => $arrData['type'] ?? null,
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
        return match (Category::query()->find($intCategoryId)?->cat_name) {
            'Tourist Destinations' => 'destinations',
            'Accommodation' => 'accommodation',
            'Food & Dining' => 'restaurants',
            'Tourist Transport' => 'transportation',
            'Travel & Tours' => 'tour-guides',
            'Farm & Agri-Tourism' => 'farm-agri-tourism',
            'Wellness & Spa' => 'wellness-spa',
            'Recreation & Activities' => 'recreation-activities',
            'MICE & Events' => 'mice-events',
            default => 'others',
        };
    }

    private function uniqueListingSlug(string $strName): string
    {
        $strBase = Str::slug($strName) ?: 'listing';
        $strSlug = $strBase;
        $intSuffix = 2;

        while (Listing::query()->where('lst_slug', $strSlug)->exists()) {
            $strSlug = "{$strBase}-{$intSuffix}";
            $intSuffix++;
        }

        return $strSlug;
    }
}
