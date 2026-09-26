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
     * Record Arrival: the two-column, Alpine.js-driven form used to log a guest.
     */
    public function record(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival');
    }

    /**
     * Submits the form (called via fetch by arrivalForm()'s submit() method in
     * resources/js/establishment.js, which then shows a toast rather than
     * reloading the page). This is the staff-entered fallback for a guest who
     * can't scan the QR, so it captures the same visit type and
     * Local/International x Male/Female x Age-group companion breakdown as
     * the public self-checkin form (see CheckinController::store) rather than
     * one visitor's own gender/classification.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'visitorName' => ['nullable', 'string', 'max:255'],
            'visitType' => ['required', Rule::in(['Daytour', 'Overnight'])],
            'male' => ['nullable', 'integer', 'min:0'],
            'female' => ['nullable', 'integer', 'min:0'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'seniors' => ['nullable', 'integer', 'min:0'],
            'local' => ['nullable', 'integer', 'min:0'],
            'foreign' => ['nullable', 'integer', 'min:0'],
        ]);

        $companions = collect(['male', 'female', 'adults', 'children', 'seniors', 'local', 'foreign'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($data[$key] ?? 0)]);

        // Resolved via establishment_id, not a Listing.name match against
        // organization_name — see Establishment\ProfileController::ownListing().
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        try {
            $listing->arrivals()->create([
                'source' => 'staff',
                'date' => $data['date'],
                'visitor_name' => $data['visitorName'] ?? null,
                'visit_type' => $data['visitType'],
                'party_male' => $companions['male'],
                'party_female' => $companions['female'],
                'party_adults' => $companions['adults'],
                'party_children' => $companions['children'],
                'party_seniors' => $companions['seniors'],
                'party_local' => $companions['local'],
                'party_foreign' => $companions['foreign'],
                'party_size' => 1 + $companions['male'] + $companions['female'],
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
