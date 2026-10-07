<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU destination management (add/edit/archive, municipality-scoped)
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
use App\Support\OperationLogger;
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
    public function destinations(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.directory.destinations', 'directory.destinations', 'Destinations', [
            'municipality' => $municipality,
            'destinations' => LguMockData::destinations($municipality),
        ]);
    }

    /**
     * Older Destinations page "Add": the same rule as Establishments ->
     * Add Tourist Attraction (AttractionRecordService) — a new destination
     * starts Not Requested and goes public only after PTO approval.
     */
    public function storeDestination(Request $request, AttractionRecordService $objRecords): RedirectResponse
    {
        $fields = $this->validatedDestinationFields($request);

        try {
            $listing = $objRecords->create($request->user(), $fields);
        } catch (\Throwable $e) {
            Log::error('Failed to create LGU destination.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return redirect()->route('lgu.directory.attractions.show', $listing)
            ->with('toast', "{$listing->name} was added. Request PTO review when it is ready to be featured.");
    }

    /**
     * Older Destinations page "Edit": edits to public content of a live
     * destination are held for PTO review (AttractionRecordService).
     */
    public function updateDestination(Request $request, Listing $listing, AttractionRecordService $objRecords): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category !== 'destinations', 404);

        $fields = $this->validatedDestinationFields($request);

        try {
            $blnIsHeldForReview = $objRecords->update($request->user(), $listing, $fields);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        } catch (\Throwable $e) {
            Log::error('Failed to update LGU destination.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', $blnIsHeldForReview ? 'Saved. Changes to the public listing were sent to the PTO for review.' : 'Destination saved.');
    }

    public function archiveDestination(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category !== 'destinations', 404);

        $before = $listing->getOriginal();

        try {
            $listing->update(['status' => 'Archived']);
        } catch (\Throwable $e) {
            Log::error('Failed to archive LGU destination.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'destination', $listing->id, $request->user()->municipality_id, null, OperationLogger::diff($before, $listing));

        return back()->with('toast', "{$listing->name} was archived.");
    }

    /**
     * "Request to feature as tourist destination" (DRAFT/UNPUBLISHED/
     * FOR_LGU_REVIEW) or "Resubmit to PTO" (FOR_CORRECTION) → Pending PTO
     * Review. Never publishes. See
     * App\Services\ListingPublishWorkflow::submitToPto().
     */
    public function submitToPto(Request $request, Listing $listing, ListingPublishWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('submit', $listing), 403);

        try {
            $workflow->submitToPto($request->user(), $listing);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->name} was sent to the Provincial Tourism Office for review. It is not published until the PTO approves it.");
    }

    /**
     * DRAFT, FOR_LGU_REVIEW, FOR_PTO_REVIEW, or FOR_CORRECTION → DRAFT,
     * with a reason the establishment sees. See App\Services\ListingPublishWorkflow::returnToEstablishment().
     */
    public function returnToEstablishment(Request $request, Listing $listing, ListingPublishWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('submit', $listing), 403);
        // A destination-only record has no establishment to return it to.
        abort_if($listing->isDestinationOnly(), 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $workflow->returnToEstablishment($request->user(), $listing, $data['reason']);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->name} was returned to the establishment.");
    }
}
