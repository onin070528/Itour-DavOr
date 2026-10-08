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
use App\Support\LguMockData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        ]);
    }

    /**
     * Older Destinations page "Add": the same rule as Establishments ->
     * Add Tourist Attraction (AttractionRecordService) — a new destination
     * starts Not Requested and goes public only after PTO approval.
     */
    public function storeDestination(Request $objRequest, AttractionRecordService $objRecords): RedirectResponse
    {
        $arrFields = $this->validatedDestinationFields($objRequest);

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

        $arrFields = $this->validatedDestinationFields($objRequest);

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
