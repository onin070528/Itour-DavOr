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
    public function record(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival', [
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
    public function store(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
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
            // Counting rule: the companion grid includes the lead visitor
            // (see arrivalForm()'s totalPeople in resources/js/establishment.js),
            // so the group's total headcount is the grid sum itself — never 0.
            $total = (int) $request->input('male', 0) + (int) $request->input('female', 0);
            if ($total < 1) {
                $validator->errors()->add('male', 'Add at least one guest to the headcount.');
            }
        })->validate();

        $companions = collect(['male', 'female', 'adults', 'children', 'seniors', 'local', 'foreign'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($data[$key] ?? 0)]);

        // Resolved via establishment_id, not a Listing.name match against
        // organization_name — see Establishment\ProfileController::ownListing().
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        try {
            $listing->arrivals()->create([
                'source' => ArrivalSource::Staff,
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
                'party_size' => $companions['male'] + $companions['female'],
                'local_origin_scope' => $data['localOriginScope'] ?? null,
                'local_origin_place' => $data['localOriginPlace'] ?? null,
                'foreign_country' => $data['foreignCountry'] ?? null,
                'status' => 'Recorded',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record arrival.', ['exception' => $e, 'listing_id' => $listing->id]);

            return response()->json(['message' => 'Something went wrong while recording the arrival. Please try again.'], 500);
        }

        return response()->json(['message' => 'Arrival recorded.']);
    }

    /**
     * Arrival Records: guest arrivals previously recorded for this
     * establishment, staff-logged and self-checkin alike. Scoped via
     * establishment_id (not a Listing.name match against organization_name —
     * see ProfileController::ownListing()), so two establishments sharing a
     * display name never see each other's arrivals.
     */
    public function index(Request $request): View
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        $arrivals = $listing->arrivals()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Arrival $arrival) => [
                'id' => 'GR-'.$arrival->id,
                'date' => $arrival->date->toDateString(),
                'visitorName' => $arrival->visitor_name,
                'gender' => $arrival->gender,
                'classification' => $arrival->classification,
                'remarks' => $arrival->remarks,
                'status' => $arrival->status,
            ]);

        return $this->renderEstablishment($request, 'establishment.arrivals.index', 'arrivals.index', 'Arrival Records', [
            'arrivals' => $arrivals,
        ]);
    }

    /**
     * Monthly Reports: this establishment's digital monthly submission
     * history, plus a "Submit [Month]" control for any of the last 12
     * months that doesn't have a report yet. Digital submission never asks
     * for new data entry here — it sums whatever Arrival rows already exist
     * for the chosen month from the Record Arrival wizard.
     */
    public function monthlyReports(Request $request): View
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        $reports = $listing->monthlyArrivalReports()
            ->with('verifier')
            ->orderByDesc('period_month')
            ->get();

        $submittedMonths = $reports->pluck('period_month')->map->toDateString();

        $monthOptions = collect(range(0, 11))
            ->map(fn (int $i) => CarbonImmutable::now()->subMonthsNoOverflow($i)->startOfMonth())
            ->reject(fn (CarbonImmutable $month) => $submittedMonths->contains($month->toDateString()))
            ->values();

        return $this->renderEstablishment($request, 'establishment.arrivals.monthly', 'arrivals.monthly', 'Monthly Reports', [
            'reports' => $reports,
            'monthOptions' => $monthOptions,
        ]);
    }

    /**
     * Submits one calendar month's report: sums that period's Arrival rows
     * (staff-logged and self-checkin alike) for this establishment into a
     * new MonthlyArrivalReport, then links those rows to it. A month with
     * no recorded arrivals still submits, as a zero-arrival report — a
     * missing report is never the same thing as a submitted zero one.
     */
    public function submitMonthlyReport(Request $request): RedirectResponse
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $month = CarbonImmutable::createFromFormat('Y-m', $data['period_month'])->startOfMonth();

        if ($listing->monthlyArrivalReports()->forPeriod($month)->exists()) {
            return back()->with('toast', "A report for {$month->format('F Y')} has already been submitted.")->with('toast_tone', 'danger');
        }

        $totals = $listing->arrivals()
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->selectRaw('
                COALESCE(SUM(party_male), 0) as party_male,
                COALESCE(SUM(party_female), 0) as party_female,
                COALESCE(SUM(party_adults), 0) as party_adults,
                COALESCE(SUM(party_children), 0) as party_children,
                COALESCE(SUM(party_seniors), 0) as party_seniors,
                COALESCE(SUM(party_local), 0) as party_local,
                COALESCE(SUM(party_foreign), 0) as party_foreign
            ')
            ->first();

        $report = DB::transaction(function () use ($listing, $month, $totals, $request) {
            $report = MonthlyArrivalReport::query()->create([
                'listing_id' => $listing->id,
                'municipality_id' => $listing->municipality_id,
                'period_month' => $month->toDateString(),
                'submission_source' => ReportSubmissionSource::Digital,
                'status' => MonthlyReportStatus::ForReview,
                'party_male' => $totals->party_male,
                'party_female' => $totals->party_female,
                'party_adults' => $totals->party_adults,
                'party_children' => $totals->party_children,
                'party_seniors' => $totals->party_seniors,
                'party_local' => $totals->party_local,
                'party_foreign' => $totals->party_foreign,
                'total_visitors' => $totals->party_male + $totals->party_female,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            $listing->arrivals()
                ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->update(['monthly_arrival_report_id' => $report->id]);

            return $report;
        });

        OperationLogger::created(
            $request->user(),
            'monthly_arrival_report',
            $report->id,
            $listing->municipality_id,
            $listing->id,
            [
                'period_month' => $month->toDateString(),
                'submission_source' => ReportSubmissionSource::Digital->value,
                'total_visitors' => $report->total_visitors,
            ],
        );

        return back()->with('toast', "{$month->format('F Y')} report submitted for LGU review.");
    }
}
