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

use App\Enums\ArrivalOriginScope;
use App\Enums\ArrivalSource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckinController extends Controller
{
    /**
     * Visitor self-registration form, reached by scanning the QR code
     * posted at a registered establishment. {establishment} is the
     * listing's uuid — the same id every establishment's QR code encodes
     * (see Establishment\ProfileController::qr), so each establishment gets
     * its own unique, stable check-in link that survives the listing's
     * slug changing later.
     *
     * Validates the QR identifier (a real, existing listing) and then
     * Listing::isQrEnabled() before showing the form at all — a Tour Guide,
     * an "Others" record, a suspended/inactive record, or a category with
     * QR scanning switched off all show a refusal message instead, per the
     * single QR rule.
     */
    public function show(string $establishment): View
    {
        $listing = Listing::query()->where('uuid', $establishment)->first();

        abort_if(! $listing, 404);

        if (! $listing->isQrEnabled()) {
            return view('lgu.establishmentQR', [
                'establishmentName' => $listing->name,
                'checkinAction' => null,
                'refusalMessage' => 'This establishment is not accepting registrations.',
            ]);
        }

        return view('lgu.establishmentQR', [
            'establishmentName' => $listing->name,
            'provinces' => config('ph_provinces'),
            'countries' => config('countries'),
            'checkinAction' => route('checkin.store', $establishment),
            'refusalMessage' => null,
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
        $listing = Listing::query()->where('uuid', $establishment)->firstOrFail();

        // Defense in depth: show() already refuses the form, but the QR
        // switch (Settings > Categories) or a status change could happen
        // between the scan and the submit — never save in that case.
        if (! $listing->isQrEnabled()) {
            return response()->json(['message' => 'This establishment is not accepting registrations.'], 422);
        }

        $data = Validator::make($request->all(), [
            'visitorName' => ['required', 'string', 'max:255'],
            'visitorContact' => ['required', 'string', 'max:255'],
            'male' => ['nullable', 'integer', 'min:0'],
            'female' => ['nullable', 'integer', 'min:0'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'seniors' => ['nullable', 'integer', 'min:0'],
            'local' => ['nullable', 'integer', 'min:0'],
            'foreign' => ['nullable', 'integer', 'min:0'],
            'localOriginScope' => [
                'nullable',
                Rule::enum(ArrivalOriginScope::class),
                Rule::prohibitedIf((int) $request->input('local', 0) <= 0),
            ],
            'localOriginPlace' => [
                'nullable', 'string', 'max:100',
                Rule::requiredIf($request->input('localOriginScope') === ArrivalOriginScope::OutsideProvince->value),
                Rule::prohibitedIf($request->input('localOriginScope') !== ArrivalOriginScope::OutsideProvince->value),
            ],
            'foreignCountry' => [
                'nullable', 'string', 'max:100',
                Rule::prohibitedIf((int) $request->input('foreign', 0) <= 0),
            ],
        ])->after(function ($validator) use ($request) {
            // Counting rule: the companion grid now includes the lead
            // visitor (see resources/js/app.js's updateTotal()), so the
            // group's total headcount is the grid sum itself — never 0.
            $total = (int) $request->input('male', 0) + (int) $request->input('female', 0);
            if ($total < 1) {
                $validator->errors()->add('male', 'Add at least one guest to the headcount.');
            }
        })->validate();

        $companions = collect(['male', 'female', 'adults', 'children', 'seniors', 'local', 'foreign'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($data[$key] ?? 0)]);

        try {
            $listing->arrivals()->create([
                'source' => ArrivalSource::SelfCheckin,
                'date' => now()->toDateString(),
                'visitor_name' => $data['visitorName'],
                'visitor_contact' => $data['visitorContact'],
                'party_male' => $companions['male'],
                'party_female' => $companions['female'],
                'party_adults' => $companions['adults'],
                'party_children' => $companions['children'],
                'party_seniors' => $companions['seniors'],
                'party_local' => $companions['local'],
                'party_foreign' => $companions['foreign'],
                'party_size' => $companions['male'] + $companions['female'],
                'local_origin_scope' => $data['localOriginScope'] ?? null,
                'local_origin_place' => $data['localOriginPlace'] ?? null,
                'foreign_country' => $data['foreignCountry'] ?? null,
                'status' => 'Recorded',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record self check-in.', ['exception' => $e, 'establishment' => $establishment]);

            return response()->json(['message' => 'Something went wrong while submitting your check-in. Please try again.'], 500);
        }

        return response()->json(['message' => 'Registration submitted.']);
    }
}
