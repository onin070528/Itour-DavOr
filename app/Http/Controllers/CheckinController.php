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

use App\Enums\ArrivalSource;
use App\Models\Listing;
use App\Services\ArrivalRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CheckinController extends Controller
{
    /** Shown on the public page and returned by store() whenever a QR can't be used. */
    public const NOT_ACCEPTING_MESSAGE = 'This establishment is not accepting registrations.';

    /**
     * Visitor self-registration form, reached by scanning the QR code
     * posted at a registered establishment. {establishment} is the
     * listing's uuid — the same id every establishment's QR code encodes
     * (see App\Services\QrCodeService), so each establishment gets its own
     * unique, stable check-in link that survives the listing's slug
     * changing later.
     *
     * Validates the QR identifier (a real, existing listing) and then
     * Listing::isAcceptingRegistrations() before showing the form at all —
     * a destination, a Tour Guide, an "Others" record, a suspended/inactive
     * record, a category or establishment with QR switched off, or an
     * establishment with no linked account all show a refusal message
     * instead.
     *
     * When the establishment also accepts public feedback
     * (Listing::isAcceptingFeedback() — published, not a tour guide), the
     * success step offers "Share Your Experience", linking to the public
     * feedback form by the listing's public slug (never its id or this
     * QR uuid). QR eligibility itself is unchanged.
     */
    public function show(string $establishment, ArrivalRecorder $arrivalRecorder): View
    {
        $objListing = Listing::query()->where('lst_uuid', $establishment)->first();

        abort_if(! $objListing, 404);

        if (! $objListing->isAcceptingRegistrations()) {
            return view('lgu.establishmentQR', [
                'establishmentName' => $objListing->lst_name,
                'checkinAction' => null,
                'refusalMessage' => self::NOT_ACCEPTING_MESSAGE,
            ]);
        }

        return view('lgu.establishmentQR', [
            'establishmentName' => $objListing->lst_name,
            'provinces' => config('ph_provinces'),
            'countries' => config('countries'),
            'municipalities' => $arrivalRecorder->getProvinceMunicipalityNames(),
            'checkinAction' => route('checkin.store', $establishment),
            'refusalMessage' => null,
            'feedbackUrl' => $objListing->isAcceptingFeedback() ? route('feedback.create', ['listing' => $objListing->lst_slug]) : null,
        ]);
    }

    /**
     * Submits the self check-in form (called via fetch by
     * initEstablishmentQrForm() in resources/js/app.js, which then shows
     * the JS-driven success step rather than reloading the page).
     * Persists one `self_checkin` arrival row — one group: the lead visitor
     * plus everyone with them as counts — with today's date and no encoder
     * (the tourist has no account). Visit type, headcount, and origin are
     * validated and saved exactly like staff Arrival Recording
     * (App\Services\ArrivalRecorder); the QR form adds the required lead
     * name/contact and the honeypot.
     */
    public function store(Request $objRequest, string $establishment, ArrivalRecorder $arrivalRecorder): JsonResponse
    {
        $objListing = Listing::query()->where('lst_uuid', $establishment)->firstOrFail();

        // Defense in depth: show() already refuses the form, but a QR switch
        // or status change could happen between the scan and the submit —
        // never save in that case.
        if (! $objListing->isAcceptingRegistrations()) {
            return response()->json(['message' => self::NOT_ACCEPTING_MESSAGE], 422);
        }

        $arrData = Validator::make($objRequest->all(), array_merge([
            // Honeypot: real visitors never see or fill this field (see
            // resources/views/lgu/establishmentQR.blade.php). A filled value
            // means a bot submitted the form, so the whole request fails
            // validation and nothing is saved.
            'website' => ['prohibited'],
            'visitorName' => ['required', 'string', 'max:255'],
            'visitorContact' => ['required', 'string', 'max:255'],
        ], $arrivalRecorder->getRules($objRequest)), [
            'visitorName.required' => 'Please enter your full name.',
            'visitorContact.required' => 'Please enter your contact number.',
            'visitType.required' => 'Please choose Day Tour or Overnight.',
            'website.prohibited' => 'Your registration could not be submitted.',
        ])
            ->after(fn ($objValidator) => $arrivalRecorder->checkHeadcount($objValidator, $objRequest))
            ->validate();

        try {
            $arrivalRecorder->record($objListing, $arrData, ArrivalSource::SelfCheckin, null, now()->toDateString());
        } catch (\Throwable $objException) {
            Log::error('Failed to record self check-in.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return response()->json(['message' => 'Something went wrong while submitting your check-in. Please try again.'], 500);
        }

        return response()->json(['message' => 'Registration submitted.']);
    }
}
