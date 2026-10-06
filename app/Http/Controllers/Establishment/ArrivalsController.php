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

use App\Enums\ArrivalOriginScope;
use App\Enums\ArrivalSource;
use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Models\Arrival;
use App\Models\MonthlyArrivalReport;
use App\Support\OperationLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArrivalsController extends EstablishmentController
{
    /**
     * Record Arrival: the two-column, Alpine.js-driven form used to log a guest.
     */
    public function record(Request $objRequest): View
    {
        return $this->renderEstablishment($objRequest, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival', [
            'provinces' => config('ph_provinces'),
            'countries' => config('countries'),
        ]);
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
    public function store(Request $objRequest): JsonResponse
    {
        $arrData = Validator::make($objRequest->all(), [
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
            // Counting rule: the companion grid includes the lead visitor
            // (see arrivalForm()'s totalPeople in resources/js/establishment.js),
            // so the group's total headcount is the grid sum itself — never 0.
            $intTotal = (int) $objRequest->input('male', 0) + (int) $objRequest->input('female', 0);
            if ($intTotal < 1) {
                $objValidator->errors()->add('male', 'Add at least one guest to the headcount.');
            }
        })->validate();

        $objCompanions = collect(['male', 'female', 'adults', 'children', 'seniors', 'local', 'foreign'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($arrData[$key] ?? 0)]);

        // Resolved via establishment_id, not a Listing.name match against
        // organization_name — see Establishment\ProfileController::ownListing().
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        try {
            $objListing->arrivals()->create([
                'arr_source' => ArrivalSource::Staff,
                'arr_date' => $arrData['date'],
                'arr_visitor_name' => $arrData['visitorName'] ?? null,
                'arr_visit_type' => $arrData['visitType'],
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
            Log::error('Failed to record arrival.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return response()->json(['message' => 'Something went wrong while recording the arrival. Please try again.'], 500);
        }

        return response()->json(['message' => 'Arrival recorded.']);
    }

    /**
     * Arrival Records: guest arrivals previously recorded for this
     * establishment, staff-logged and self-checkin alike. Scoped via
     * lst_id (not a Listing.name match against usr_organization_name —
     * see ProfileController::ownListing()), so two establishments sharing a
     * display name never see each other's arrivals.
     */
    public function index(Request $objRequest): View
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $objArrivals = $objListing->arrivals()
            ->orderByDesc('arr_date')
            ->orderByDesc('arr_id')
            ->get()
            ->map(fn (Arrival $objArrival) => [
                'id' => 'GR-'.$objArrival->arr_id,
                'date' => $objArrival->arr_date->toDateString(),
                'visitorName' => $objArrival->arr_visitor_name,
                'gender' => $objArrival->arr_gender,
                'classification' => $objArrival->arr_classification,
                'remarks' => $objArrival->arr_remarks,
                'status' => $objArrival->arr_status,
            ]);

        return $this->renderEstablishment($objRequest, 'establishment.arrivals.index', 'arrivals.index', 'Arrival Records', [
            'arrivals' => $objArrivals,
        ]);
    }

    /**
     * Monthly Reports: this establishment's digital monthly submission
     * history, plus a "Submit [Month]" control for any of the last 12
     * months that doesn't have a report yet. Digital submission never asks
     * for new data entry here — it sums whatever Arrival rows already exist
     * for the chosen month from the Record Arrival wizard.
     */
    public function monthlyReports(Request $objRequest): View
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $objReports = $objListing->monthlyArrivalReports()
            ->with('verifier')
            ->orderByDesc('mar_period_month')
            ->get();

        $strSubmittedMonths = $objReports->pluck('mar_period_month')->map->toDateString();

        $objMonthOptions = collect(range(0, 11))
            ->map(fn (int $intIndex) => CarbonImmutable::now()->subMonthsNoOverflow($intIndex)->startOfMonth())
            ->reject(fn (CarbonImmutable $dtmMonth) => $strSubmittedMonths->contains($dtmMonth->toDateString()))
            ->values();

        return $this->renderEstablishment($objRequest, 'establishment.arrivals.monthly', 'arrivals.monthly', 'Monthly Reports', [
            'reports' => $objReports,
            'monthOptions' => $objMonthOptions,
        ]);
    }

    /**
     * Submits one calendar month's report: sums that period's Arrival rows
     * (staff-logged and self-checkin alike) for this establishment into a
     * new MonthlyArrivalReport, then links those rows to it. A month with
     * no recorded arrivals still submits, as a zero-arrival report — a
     * missing report is never the same thing as a submitted zero one.
     */
    public function submitMonthlyReport(Request $objRequest): RedirectResponse
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $dtmMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();

        if ($objListing->monthlyArrivalReports()->forPeriod($dtmMonth)->exists()) {
            return back()->with('toast', "A report for {$dtmMonth->format('F Y')} has already been submitted.")->with('toast_tone', 'danger');
        }

        $objTotals = $objListing->arrivals()
            ->whereBetween('arr_date', [$dtmMonth->toDateString(), $dtmMonth->endOfMonth()->toDateString()])
            ->selectRaw('
                COALESCE(SUM(arr_party_male), 0) as party_male,
                COALESCE(SUM(arr_party_female), 0) as party_female,
                COALESCE(SUM(arr_party_adults), 0) as party_adults,
                COALESCE(SUM(arr_party_children), 0) as party_children,
                COALESCE(SUM(arr_party_seniors), 0) as party_seniors,
                COALESCE(SUM(arr_party_local), 0) as party_local,
                COALESCE(SUM(arr_party_foreign), 0) as party_foreign
            ')
            ->first();

        try {
            $objReport = DB::transaction(function () use ($objListing, $dtmMonth, $objTotals, $objRequest) {
                $objReport = MonthlyArrivalReport::query()->create([
                    'lst_id' => $objListing->lst_id,
                    'mun_id' => $objListing->mun_id,
                    'mar_period_month' => $dtmMonth->toDateString(),
                    'mar_submission_source' => ReportSubmissionSource::Digital,
                    'mar_status' => MonthlyReportStatus::ForReview,
                    'mar_party_male' => $objTotals->party_male,
                    'mar_party_female' => $objTotals->party_female,
                    'mar_party_adults' => $objTotals->party_adults,
                    'mar_party_children' => $objTotals->party_children,
                    'mar_party_seniors' => $objTotals->party_seniors,
                    'mar_party_local' => $objTotals->party_local,
                    'mar_party_foreign' => $objTotals->party_foreign,
                    'mar_total_visitors' => $objTotals->party_male + $objTotals->party_female,
                    'mar_submitted_by' => $objRequest->user()->usr_id,
                    'mar_submitted_at' => now(),
                ]);

                $objListing->arrivals()
                    ->whereBetween('arr_date', [$dtmMonth->toDateString(), $dtmMonth->endOfMonth()->toDateString()])
                    ->update(['mar_id' => $objReport->mar_id]);

                return $objReport;
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to submit the monthly arrival report.', ['exception' => $objException, 'lst_id' => $objListing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created(
            $objRequest->user(),
            'monthly_arrival_report',
            $objReport->mar_id,
            $objListing->mun_id,
            $objListing->lst_id,
            [
                'period_month' => $dtmMonth->toDateString(),
                'submission_source' => ReportSubmissionSource::Digital->value,
                'total_visitors' => $objReport->mar_total_visitors,
            ],
        );

        return back()->with('toast', "{$dtmMonth->format('F Y')} report submitted for LGU review.");
    }
}
