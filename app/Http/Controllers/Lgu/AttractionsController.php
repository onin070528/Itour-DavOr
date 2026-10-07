<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : LGU tourist attractions (destination-only records) — add with photos, view, and edit, scoped to the LGU's own municipality.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Requests\SaveAttractionRequest;
use App\Models\Listing;
use App\Services\AttractionRecordService;
use App\Services\EstablishmentImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Attractions are listed on the Establishments page (Attractions view,
 * D1). They have no account, QR, or reporting method; they reach the
 * public site only through the PTO destination review — the same
 * request / resubmit actions as an establishment
 * (Lgu\DirectoryController::submitToPto()).
 */
class AttractionsController extends LguController
{
    use AuthorizesOwnMunicipality;

    public function create(Request $request): View
    {
        return $this->renderLgu($request, 'lgu.directory.attractions.create', 'directory.establishments', 'Add Tourist Attraction');
    }

    /**
     * Saves the attraction (Not Requested), then (optionally) its photos
     * through the existing photo workflow — LGU uploads go to the PTO for
     * approval. A photo problem never loses the attraction.
     */
    public function store(SaveAttractionRequest $request, AttractionRecordService $objRecords, EstablishmentImageUploader $objUploader): RedirectResponse
    {
        $objLgu = $request->user();

        try {
            $objListing = $objRecords->create($objLgu, $request->attractionFields());
        } catch (\Throwable $e) {
            Log::error('Failed to create LGU attraction.', ['exception' => $e]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $arrPhotos = $request->file('photos', []);

        if ($arrPhotos === []) {
            return redirect()->route('lgu.directory.attractions.show', $objListing)
                ->with('toast', "{$objListing->name} was added. Request PTO review when it is ready to be featured.");
        }

        // Summary comment: photos go through the unchanged photo workflow.
        try {
            $objUploader->upload($objLgu, $objListing, $arrPhotos, $request->validated('credit'));
        } catch (ValidationException $e) {
            return redirect()->route('lgu.directory.attractions.edit', $objListing)
                ->withFragment('photos')
                ->with('toast', "{$objListing->name} was added, but the photos were not: {$e->validator->errors()->first()}")
                ->with('toast_tone', 'danger');
        } catch (\Throwable $e) {
            Log::error('Failed to upload photos for a new LGU attraction.', ['exception' => $e, 'listing_id' => $objListing->id]);

            return redirect()->route('lgu.directory.attractions.edit', $objListing)
                ->withFragment('photos')
                ->with('toast', "{$objListing->name} was added, but the photos could not be uploaded. Please try again below.")
                ->with('toast_tone', 'danger');
        }

        return redirect()->route('lgu.directory.attractions.show', $objListing)
            ->with('toast', "{$objListing->name} was added. Its photos were sent to the PTO for approval.");
    }

    /**
     * Attraction details: information, photos, and destination listing state.
     */
    public function show(Request $request, Listing $listing): View
    {
        $this->_authorizeOwnAttraction($request, $listing);

        $listing->load('establishmentImages');

        return $this->renderLgu($request, 'lgu.directory.attractions.show', 'directory.establishments', $listing->name, [
            'listing' => $listing,
        ]);
    }

    public function edit(Request $request, Listing $listing): View
    {
        $this->_authorizeOwnAttraction($request, $listing);

        $listing->load('establishmentImages');

        return $this->renderLgu($request, 'lgu.directory.attractions.edit', 'directory.establishments', "Edit {$listing->name}", [
            'listing' => $listing,
            // A live attraction's held changes are what the LGU is editing.
            'formListing' => $listing->withPendingChanges(),
        ]);
    }

    /**
     * Authorization (own municipality, security-logged) happens in
     * SaveAttractionRequest::authorize(). See
     * AttractionRecordService::update() for what is held for PTO review.
     */
    public function update(SaveAttractionRequest $request, Listing $listing, AttractionRecordService $objRecords): RedirectResponse
    {
        try {
            $blnIsHeldForReview = $objRecords->update($request->user(), $listing, $request->attractionFields());
        } catch (ValidationException $e) {
            return redirect()->route('lgu.directory.attractions.show', $listing)
                ->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        } catch (\Throwable $e) {
            Log::error('Failed to update LGU attraction.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $strToast = $blnIsHeldForReview
            ? 'Saved. Changes to the public listing were sent to the PTO for review — the published version stays live until they are approved.'
            : 'Attraction saved.';

        return redirect()->route('lgu.directory.attractions.show', $listing)->with('toast', $strToast);
    }

    /**
     * Own municipality (403 + security log otherwise), destination-only
     * records only — an establishment is not an attraction page (404).
     */
    private function _authorizeOwnAttraction(Request $request, Listing $objListing): void
    {
        $this->authorizeOwnMunicipality($request, $objListing);
        abort_unless($objListing->isDestinationOnly(), 404);
    }
}
