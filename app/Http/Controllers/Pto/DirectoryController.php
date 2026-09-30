<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide destination management (add/edit/archive) and
 * read-only monitoring of accredited establishments and the tourism map.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\Listing;
use App\Models\Municipality;
use App\Support\OperationLogger;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DirectoryController extends PtoController
{
    use ManagesDestinationListings;

    /**
     * Destinations: province-wide destination management.
     */
    public function destinations(Request $request): View
    {
        return $this->renderPto($request, 'pto.directory.destinations', 'directory.destinations', 'Destinations', [
            'destinations' => TourismCatalog::listings(),
            'categories' => TourismCatalog::categories(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }

    public function storeDestination(Request $request): RedirectResponse
    {
        $fields = $this->validatedDestinationFields($request);
        $municipality = $this->validatedMunicipality($request);

        $municipalityId = $this->municipalityIdByName($municipality);

        try {
            $listing = $this->createDestination($fields, $municipality, $municipalityId);
        } catch (\Throwable $e) {
            Log::error('Failed to create PTO destination.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        // createDestination() doesn't set municipality_id (see
        // ManagesDestinationListings) — resolved here by name instead of
        // trusting the just-created row's (currently always-null) FK.
        OperationLogger::created($request->user(), 'destination', $listing->id, $this->municipalityIdByName($municipality), null, [
            'name' => $listing->name,
            'barangay' => $listing->barangay,
            'municipality' => $listing->municipality,
        ]);

        return back()->with('toast', "{$listing->name} was added.");
    }

    public function updateDestination(Request $request, Listing $listing): RedirectResponse
    {
        abort_if($listing->category !== 'destinations', 404);

        $fields = $this->validatedDestinationFields($request);
        $municipality = $this->validatedMunicipality($request);
        $before = $listing->getOriginal();

        try {
            $listing->update([...$fields, 'municipality' => $municipality, 'municipality_id' => $this->municipalityIdByName($municipality)]);
        } catch (\Throwable $e) {
            Log::error('Failed to update PTO destination.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'destination', $listing->id, $this->municipalityIdByName($municipality), null, OperationLogger::diff($before, $listing));

        return back()->with('toast', 'Destination saved.');
    }

    public function archiveDestination(Request $request, Listing $listing): RedirectResponse
    {
        abort_if($listing->category !== 'destinations', 404);

        $before = $listing->getOriginal();

        try {
            $listing->update(['status' => 'Archived']);
        } catch (\Throwable $e) {
            Log::error('Failed to archive PTO destination.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'destination', $listing->id, $this->municipalityIdByName($listing->municipality), null, OperationLogger::diff($before, $listing));

        return back()->with('toast', "{$listing->name} was archived.");
    }

    private function municipalityIdByName(string $municipality): ?int
    {
        return Municipality::query()->where('name', $municipality)->value('id');
    }

    /**
     * Establishments: accredited tourism establishments, province-wide.
     */
    public function establishments(Request $request): View
    {
        // `status` (Active / Pending Review / Inactive) comes straight from
        // the listings table now — real accreditation state, editable via
        // Lgu\DirectoryController@verifyEstablishment.
        $listings = collect(TourismCatalog::listings())
            ->where('category', '!=', 'destinations')
            ->values();

        return $this->renderPto($request, 'pto.directory.establishments', 'directory.establishments', 'Establishments', [
            'listings' => $listings->all(),
            'categories' => TourismCatalog::categories(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }

    /**
     * Map: Mapbox tourism map (satellite/streets + 3D terrain) plotting every
     * listing with coordinates.
     */
    public function map(Request $request): View
    {
        return $this->renderPto($request, 'pto.directory.map', 'directory.map', 'Tourism Map', [
            'listings' => TourismCatalog::listings(),
        ]);
    }
}
