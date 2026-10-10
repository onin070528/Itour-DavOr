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
        $objListing = Listing::query()->where('lst_uuid', $establishment)->first();

        abort_if(! $objListing, 404);

        if (! $objListing->isQrEnabled()) {
            return view('lgu.establishmentQR', [
                'establishmentName' => $objListing->lst_name,
                'checkinAction' => null,
                'refusalMessage' => 'This establishment is not accepting registrations.',
            ]);
        }

        return view('lgu.establishmentQR', [
            'establishmentName' => $objListing->lst_name,
            'provinces' => config('ph_provinces'),
            'countries' => config('countries'),
            'checkinAction' => route('checkin.store', $establishment),
            'refusalMessage' => null,
            'feedbackUrl' => $objListing->isPubliclyVisible() ? route('listings.show', $objListing).'#feedback' : null,
        ]);
    }

    /**
     * Submits the self check-in form (called via fetch by
     * initEstablishmentQrForm() in resources/js/app.js, which then shows
     * the existing JS-driven success step rather than reloading the page).
     * Persists one `self_checkin` arrival row — a party, described by the
     * counters on the form — against the scanned establishment.
     */
    public function store(Request $objRequest, string $establishment): JsonResponse
    {
        $objListing = Listing::query()->where('lst_uuid', $establishment)->firstOrFail();

        // Defense in depth: show() already refuses the form, but the QR
        // switch (Settings > Categories) or a status change could happen
        // between the scan and the submit — never save in that case.
        if (! $objListing->isQrEnabled()) {
            return response()->json(['message' => 'This establishment is not accepting registrations.'], 422);
        }

        $arrData = Validator::make($objRequest->all(), [
            // Honeypot: real visitors never see or fill this field (see
            // resources/views/lgu/establishmentQR.blade.php). A filled value
            // means a bot submitted the form, so the whole request fails
            // validation and nothing is saved.
            'website' => ['prohibited'],
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
                Rule::prohibitedIf((int) $objRequest->input('local', 0) <= 0),
            ],
            'localOriginPlace' => [
                'nullable', 'string', 'max:100',
                Rule::requiredIf($objRequest->input('localOriginScope') === ArrivalOriginScope::OutsideProvince->value),
                Rule::prohibitedIf($objRequest->input('localOriginScope') !== ArrivalOriginScope::OutsideProvince->value),
            ],
            'foreignCountry' => [
                'nullable', 'string', 'max:100',
                Rule::prohibitedIf((int) $objRequest->input('foreign', 0) <= 0),
            ],
        ])->after(function ($objValidator) use ($objRequest) {
            // Counting rule: the companion grid now includes the lead
            // visitor (see resources/js/app.js's updateTotal()), so the
            // group's total headcount is the grid sum itself — never 0.
            $intTotal = (int) $objRequest->input('male', 0) + (int) $objRequest->input('female', 0);
            if ($intTotal < 1) {
                $objValidator->errors()->add('male', 'Add at least one guest to the headcount.');
            }
        })->validate();

        $objCompanions = collect(['male', 'female', 'adults', 'children', 'seniors', 'local', 'foreign'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($arrData[$key] ?? 0)]);

        try {
            $objListing->arrivals()->create([
                'arr_source' => ArrivalSource::SelfCheckin,
                'arr_date' => now()->toDateString(),
                'arr_visitor_name' => $arrData['visitorName'],
                'arr_visitor_contact' => $arrData['visitorContact'],
                'arr_party_male' => $objCompanions['male'],
                'arr_party_female' => $objCompanions['female'],
                'arr_party_adults' => $objCompanions['adults'],
                'arr_party_children' => $objCompanions['children'],
                'arr_party_seniors' => $objCompanions['seniors'],
                'arr_party_local' => $objCompanions['local'],
                'arr_party_foreign' => $objCompanions['foreign'],
                'arr_party_size' => $objCompanions['male'] + $objCompanions['female'],
                'arr_local_origin_scope' => $arrData['localOriginScope'] ?? null,
                'arr_local_origin_place' => $arrData['localOriginPlace'] ?? null,
                'arr_foreign_country' => $arrData['foreignCountry'] ?? null,
                'arr_status' => 'Recorded',
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to record self check-in.', ['exception' => $objException, 'establishment' => $establishment]);

            return response()->json(['message' => 'Something went wrong while submitting your check-in. Please try again.'], 500);
        }

        return response()->json(['message' => 'Registration submitted.']);
    }
}
