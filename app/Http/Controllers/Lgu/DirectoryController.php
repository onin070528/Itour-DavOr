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

use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\Listing;
use App\Support\LguMockData;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function storeDestination(Request $request): RedirectResponse
    {
        $fields = $this->validatedDestinationFields($request);
        $lgu = $request->user();
        $municipality = $lgu->organization_subtitle;

        try {
            $listing = $this->createDestination($fields, $municipality, $lgu->municipality_id);
        } catch (\Throwable $e) {
            Log::error('Failed to create LGU destination.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($lgu, 'destination', $listing->id, $lgu->municipality_id, null, [
            'name' => $listing->name,
            'barangay' => $listing->barangay,
            'municipality' => $listing->municipality,
        ]);

        return back()->with('toast', "{$listing->name} was added.");
    }

    public function updateDestination(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category !== 'destinations', 404);

        $fields = $this->validatedDestinationFields($request);
        $before = $listing->getOriginal();

        try {
            $listing->update($fields);
        } catch (\Throwable $e) {
            Log::error('Failed to update LGU destination.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'destination', $listing->id, $request->user()->municipality_id, null, OperationLogger::diff($before, $listing));

        return back()->with('toast', 'Destination saved.');
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
     * Establishments: view/monitor access only — no edit or delete controls.
     */
    public function establishments(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.directory.establishments', 'directory.establishments', 'Establishments', [
            'municipality' => $municipality,
            'listings' => LguMockData::establishments($municipality),
        ]);
    }

    public function verifyEstablishment(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);

        abort_if($listing->category === 'destinations', 404);

        $before = $listing->getOriginal();

        try {
            $listing->update(['status' => 'Active']);
        } catch (\Throwable $e) {
            Log::error('Failed to verify establishment.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::validated($request->user(), 'establishment', $listing->id, $listing->municipality_id, $listing->id, OperationLogger::diff($before, $listing));

        return back()->with('toast', "{$listing->name} marked as verified.");
    }
}
