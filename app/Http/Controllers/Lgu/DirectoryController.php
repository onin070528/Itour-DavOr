<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU destination management (add/edit, municipality-scoped)
 * and the destination listing actions on establishments (submit to PTO,
 * return to the establishment). Establishment management itself lives in
 * Lgu\EstablishmentsController.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\Listing;
use App\Services\AttractionRecordService;
use App\Services\ListingPublishWorkflow;
use App\Support\BusinessHours;
use App\Support\LguMockData;
use App\Support\OperationLogger;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $objListing = $objRecords->create($objRequest->user(), $arrFields);
        } catch (\Throwable $objException) {
            Log::error('Failed to create LGU destination.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return redirect()->route('lgu.directory.attractions.show', $objListing)
            ->with('toast', "{$objListing->lst_name} was added. Request PTO review when it is ready to be featured.");
    }

    /**
     * Older Destinations page "Edit": edits to public content of a live
     * destination are held for PTO review (AttractionRecordService).
     */
    public function updateDestination(Request $objRequest, Listing $listing, AttractionRecordService $objRecords): RedirectResponse
    {
        $this->authorizeOwnMunicipality($objRequest, $listing);
        abort_if($listing->lst_category !== 'destinations', 404);
        // A PTO-managed destination is never edited by the LGU (D10).
        abort_unless($objRequest->user()->can('update', $listing), 403);

        $arrFields = $this->_validatedLguDestinationFields($objRequest);
        $arrBefore = $listing->getOriginal();

        try {
            $blnIsHeldForReview = $objRecords->update($objRequest->user(), $listing, $arrFields);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        } catch (\Throwable $objException) {
            Log::error('Failed to update LGU destination.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', $blnIsHeldForReview ? 'Saved. Changes to the public listing were sent to the PTO for review.' : 'Destination saved.');
    }

    /**
     * Archiving is PTO-only (Objective 3, D3; Pto\DirectoryController::
     * updateStatus()). The LGU interface no longer offers it; this route
     * stays only so a direct LGU request is answered with 403 and recorded
     * by SecurityLogger::accessDenied() (Gate::after in AppServiceProvider)
     * instead of silently doing nothing. A cross-municipality attempt is
     * still logged as a municipality-scope denial first; an own-municipality
     * attempt is denied by ListingPolicy::archive(), which never allows an
     * LGU account, so the redirect below is never reached here.
     */
    public function archiveDestination(Request $objRequest, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($objRequest, $listing);
        abort_unless($objRequest->user()->can('archive', $listing), 403);

        return back();
    }

    /**
     * "Request to feature as tourist destination" (DRAFT/UNPUBLISHED/
     * FOR_LGU_REVIEW) or "Resubmit to PTO" (FOR_CORRECTION) → Pending PTO
     * Review. Never publishes. See
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

        return back()->with('toast', "{$listing->lst_name} was sent to the Provincial Tourism Office for review. It is not published until the PTO approves it.");
    }

    /**
     * DRAFT, FOR_LGU_REVIEW, FOR_PTO_REVIEW, or FOR_CORRECTION → DRAFT,
     * with a reason the establishment sees. See App\Services\ListingPublishWorkflow::returnToEstablishment().
     */
    public function returnToEstablishment(Request $objRequest, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_unless($objRequest->user()->can('submit', $listing), 403);
        // A destination-only record has no establishment to return it to.
        abort_if($listing->isDestinationOnly(), 404);

        $arrData = $objRequest->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $objWorkflow->returnToEstablishment($objRequest->user(), $listing, $arrData['reason']);
        } catch (ValidationException $objException) {
            return back()->with('toast', $objException->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->lst_name} was returned to the establishment.");
    }
}
