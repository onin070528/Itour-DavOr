<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU destination management (add/edit/archive, municipality-scoped)
 * and read-only monitoring of establishments in the account's municipality.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\ImageStatus;
use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Services\ListingPublishWorkflow;
use App\Support\BusinessHours;
use App\Support\LguMockData;
use App\Support\OperationLogger;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DirectoryController extends LguController
{
    use AuthorizesOwnMunicipality, ManagesDestinationListings;

    /**
     * Destinations: full management access, scoped to this municipality.
     * New/edited destinations are always saved under the LGU's own
     * municipality — there is no municipality selector in the form.
     */
    public function destinations(Request $objRequest): View
    {
        $objMunicipality = $objRequest->user()->usr_organization_subtitle;

        return $this->renderLgu($objRequest, 'lgu.directory.destinations', 'directory.destinations', 'Destinations', [
            'municipality' => $objMunicipality,
            'destinations' => LguMockData::destinations($objMunicipality),
            'barangays' => TourismCatalog::barangaysFor($objMunicipality),
            'contactOffice' => TourismCatalog::tourismOfficeName($objMunicipality),
        ]);
    }

    /**
     * Same fields as the shared validation, but the barangay must belong to
     * the LGU's own municipality and the contact office is always that
     * municipality's tourism office (the form's field is locked).
     *
     * @return array<string, mixed>
     */
    private function _validatedLguDestinationFields(Request $objRequest): array
    {
        $strMunicipality = $objRequest->user()->usr_organization_subtitle;

        $objRequest->validate([
            'barangay' => ['required', 'string', Rule::in(TourismCatalog::barangaysFor($strMunicipality))],
        ]);

        $arrFields = $this->validatedDestinationFields($objRequest);
        $arrFields['lst_contact_office'] = TourismCatalog::tourismOfficeName($strMunicipality);

        return [...$arrFields, ...$this->_validatedOptionalDestinationFields($objRequest)];
    }

    /**
     * The Accounts form (same layout as the establishment form) also sends
     * business hours, a contact person, a public email and a website. They
     * are optional and only applied when sent, so the Destinations
     * directory's own Edit modal (which has none of them) never blanks
     * what was saved from the Accounts form.
     *
     * @return array<string, mixed>
     */
    private function _validatedOptionalDestinationFields(Request $objRequest): array
    {
        $arrData = $objRequest->validate([
            'ownerName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            ...BusinessHours::validationRules($objRequest),
        ], BusinessHours::validationMessages());

        $arrFields = [];

        if ($objRequest->has('ownerName')) {
            $arrFields['lst_owner_name'] = $arrData['ownerName'] ?? null;
        }

        if ($objRequest->has('email')) {
            $arrFields['lst_email'] = $arrData['email'] ?? null;
        }

        if ($objRequest->has('website')) {
            $arrFields['lst_website'] = $arrData['website'] ?? null;
        }

        if ($objRequest->has('hoursOpen')) {
            $arrFields['lst_hours'] = BusinessHours::format($arrData['hoursDays'] ?? null, $arrData['hoursOpen'] ?? null, $arrData['hoursClose'] ?? null);
        }

        return $arrFields;
    }

    public function storeDestination(Request $objRequest): RedirectResponse
    {
        $arrFields = $this->_validatedLguDestinationFields($objRequest);
        $objLgu = $objRequest->user();
        $objMunicipality = $objLgu->usr_organization_subtitle;

        try {
            $objListing = $this->createDestination($arrFields, $objMunicipality, $objLgu->mun_id);
        } catch (\Throwable $objException) {
            Log::error('Failed to create LGU destination.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($objLgu, 'destination', $objListing->lst_id, $objLgu->mun_id, null, [
            'name' => $objListing->lst_name,
            'barangay' => $objListing->lst_barangay,
            'municipality' => $objListing->lst_municipality,
        ]);

        return back()->with('toast', "{$objListing->lst_name} was added.");
    }

    public function updateDestination(Request $objRequest, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($objRequest, $listing);
        abort_if($listing->lst_category !== 'destinations', 404);

        $arrFields = $this->_validatedLguDestinationFields($objRequest);
        $arrBefore = $listing->getOriginal();

        try {
            $listing->update($arrFields);
        } catch (\Throwable $objException) {
            Log::error('Failed to update LGU destination.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'destination', $listing->lst_id, $objRequest->user()->mun_id, null, OperationLogger::diff($arrBefore, $listing));

        return back()->with('toast', 'Destination saved.');
    }

    public function archiveDestination(Request $objRequest, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($objRequest, $listing);
        abort_if($listing->lst_category !== 'destinations', 404);

        $arrBefore = $listing->getOriginal();

        try {
            $listing->update(['lst_status' => 'Archived']);
        } catch (\Throwable $objException) {
            Log::error('Failed to archive LGU destination.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'destination', $listing->lst_id, $objRequest->user()->mun_id, null, OperationLogger::diff($arrBefore, $listing));

        return back()->with('toast', "{$listing->lst_name} was archived.");
    }

    /**
     * Establishments: view/monitor access only — no edit or delete controls.
     */
    public function establishments(Request $objRequest): View
    {
        $objMunicipality = $objRequest->user()->usr_organization_subtitle;

        return $this->renderLgu($objRequest, 'lgu.directory.establishments', 'directory.establishments', 'Establishments', [
            'municipality' => $objMunicipality,
            'listings' => LguMockData::establishments($objMunicipality),
            'photoLastUpdated' => $this->_photoLastUpdatedBySlug($objRequest->user()->mun_id),
        ]);
    }

    /**
     * "Photo last updated" — the date of each establishment's latest
     * PUBLISHED image, keyed by slug (what the mock-shaped $listings rows
     * use as their 'id').
     *
     * @return array<string, Carbon>
     */
    private function _photoLastUpdatedBySlug(?int $intMunicipalityId): array
    {
        if ($intMunicipalityId === null) {
            return [];
        }

        return EstablishmentImage::query()
            ->where('img_status', ImageStatus::Published->value)
            ->whereHas('listing', fn ($objQuery) => $objQuery->where('mun_id', $intMunicipalityId))
            ->with('listing:lst_id,lst_slug')
            ->get()
            ->groupBy('listing.lst_slug')
            ->map(fn ($objImages) => $objImages->max('img_updated_at'))
            ->all();
    }

    /**
     * DRAFT/UNPUBLISHED → FOR_PTO_REVIEW. See
     * App\Services\ListingPublishWorkflow::submitToPto().
     */
    public function submitToPto(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('submit', $listing), 403);

        try {
            $objWorkflow->submitToPto($objRequest->user(), $listing);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} was submitted to the Provincial Tourism Office.");
    }

    /**
     * DRAFT or FOR_PTO_REVIEW → DRAFT, with a reason the establishment
     * sees. See App\Services\ListingPublishWorkflow::returnToEstablishment().
     */
    public function returnToEstablishment(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('submit', $listing), 403);

        $arrData = $objRequest->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $objWorkflow->returnToEstablishment($objRequest->user(), $listing, $arrData['reason']);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} was returned to the establishment.");
    }
}
