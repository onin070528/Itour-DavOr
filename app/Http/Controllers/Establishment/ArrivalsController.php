<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Handles the Establishment role's manual arrival-recording wizard —
 * showing the form and persisting a staff-entered guest arrival.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArrivalsController extends EstablishmentController
{
    /**
     * Record Arrival: the Enter → Review → Submit wizard used to log a guest.
     */
    public function record(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival');
    }

    /**
     * Submits the wizard (called via fetch by initArrivalWizard() in
     * resources/js/establishment.js, which then shows the existing
     * JS-driven success step rather than reloading the page).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'visitorName' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'classification' => ['required', Rule::in(['Local (Same Province)', 'Domestic (Other Province)', 'Foreign'])],
            'remarks' => ['nullable', 'string'],
        ]);

        // Resolved via establishment_id, not a Listing.name match against
        // organization_name — see Establishment\ProfileController::ownListing().
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        try {
            $listing->arrivals()->create([
                'source' => 'staff',
                'date' => $data['date'],
                'visitor_name' => $data['visitorName'] ?? null,
                'gender' => $data['gender'],
                'classification' => $data['classification'],
                'remarks' => $data['remarks'] ?? null,
                'party_size' => 1,
                'status' => 'Recorded',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record arrival.', ['exception' => $e, 'listing_id' => $listing->id]);

            return response()->json(['message' => 'Something went wrong while recording the arrival. Please try again.'], 500);
        }

        return response()->json(['message' => 'Arrival recorded.']);
    }

    /**
     * Arrival Records: guest arrivals previously recorded for this establishment.
     */
    public function index(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.arrivals.index', 'arrivals.index', 'Arrival Records', [
            'arrivals' => EstablishmentMockData::arrivals($name),
        ]);
    }
}
