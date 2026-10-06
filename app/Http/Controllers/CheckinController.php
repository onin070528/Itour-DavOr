<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Handles the tourist-facing QR self check-in flow — showing the
 * self-registration form and persisting the submitted arrival.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\TourismCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckinController extends Controller
{
    /**
     * Visitor self-registration form, reached by scanning the QR code
     * posted at a registered establishment. {establishment} is the
     * listing's id slug (e.g. "dahican-surf-guides") — the same id every
     * establishment's QR code encodes (see Establishment\ProfileController
     * ::qr), so each establishment gets its own unique, stable check-in
     * link and the form always knows which establishment it's for.
     */
    public function show(string $establishment): View
    {
        $listing = collect(TourismCatalog::listings())->firstWhere('id', $establishment);

        abort_if(! $listing, 404);

        return view('lgu.establishmentQR', [
            'establishmentName' => $listing['name'],
            'checkinAction' => route('checkin.store', $establishment),
        ]);
    }

    /**
     * Submits the self check-in form (called via fetch by
     * initEstablishmentQrForm() in resources/js/app.js, which then shows
     * the existing JS-driven success step rather than reloading the page).
     * Persists one `self_checkin` arrival row — a party, described by the
     * counters on the form — against the scanned establishment.
     */
    public function store(Request $request, string $establishment): JsonResponse
    {
        $listing = Listing::query()->where('lst_slug', $establishment)->firstOrFail();

        $data = $request->validate([
            'visitorName' => ['required', 'string', 'max:255'],
            'visitorContact' => ['required', 'string', 'max:255'],
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

        try {
            $listing->arrivals()->create([
                'arr_source' => 'self_checkin',
                'arr_date' => now()->toDateString(),
                'arr_visitor_name' => $data['visitorName'],
                'arr_visitor_contact' => $data['visitorContact'],
                'arr_party_male' => $companions['male'],
                'arr_party_female' => $companions['female'],
                'arr_party_adults' => $companions['adults'],
                'arr_party_children' => $companions['children'],
                'arr_party_seniors' => $companions['seniors'],
                'arr_party_local' => $companions['local'],
                'arr_party_foreign' => $companions['foreign'],
                'arr_party_size' => 1 + $companions['male'] + $companions['female'],
                'arr_status' => 'Recorded',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record self check-in.', ['exception' => $e, 'establishment' => $establishment]);

            return response()->json(['message' => 'Something went wrong while submitting your check-in. Please try again.'], 500);
        }

        return response()->json(['message' => 'Registration submitted.']);
    }
}
