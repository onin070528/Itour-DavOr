<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU review of establishment monthly tourist-arrival reports —
 * the per-establishment status table, Manual/Paper encoding for
 * establishments that can't submit digitally yet, verification, and
 * consolidation of verified reports into a MunicipalReport submitted to PTO.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Controllers\Concerns\TracksReportHistory;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\MunicipalReport;
use App\Support\Notifier;
use App\Support\OperationLogger;
use App\Support\ReportWorkflowSteps;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MonthlyReportsController extends LguController
{
    use AuthorizesOwnMunicipality, TracksReportHistory;

    /**
     * The establishment × month status table for the account's municipality.
     * Establishments with no MonthlyArrivalReport row for the selected
     * period are shown as "Not Submitted" — a display-only state computed
     * here, never written to the database.
     */
    public function index(Request $objRequest): View
    {
        $objUser = $objRequest->user();
        $dtmMonth = $this->resolvePeriod($objRequest);

        $objEstablishments = Listing::query()
            ->visibleTo($objUser)
            ->with('categoryRecord')
            ->orderBy('lst_name')
            ->get()
            ->filter(fn (Listing $objListing) => $objListing->requiresArrivalRecords())
            ->values();

        $objReportsByListing = MonthlyArrivalReport::query()
            ->visibleTo($objUser)
            ->forPeriod($dtmMonth)
            ->with(['submitter', 'verifier'])
            ->get()
            ->keyBy('lst_id');

        // "Not Submitted" rows first, then For Review, then Verified — the
        // ones needing LGU attention surface at the top of the table.
        $arrStatusPriority = ['Not Submitted' => 0, 'For Review' => 1, 'Verified' => 2];

        $objRows = $objEstablishments->map(function (Listing $objListing) use ($objReportsByListing) {
            $objReport = $objReportsByListing->get($objListing->lst_id);

            return [
                'listing' => $objListing,
                'report' => $objReport,
                'status' => $objReport?->mar_status->label() ?? 'Not Submitted',
            ];
        })->sortBy(fn (array $arrRow) => $arrStatusPriority[$arrRow['status']])->values();

        $intMissingCount = $objRows->whereNull('report')->count();
        $intForReviewCount = $objRows->filter(fn (array $arrRow) => $arrRow['report']?->mar_status === MonthlyReportStatus::ForReview)->count();
        $intVerifiedCount = $objRows->filter(fn (array $arrRow) => $arrRow['report']?->mar_status === MonthlyReportStatus::Verified)->count();
        $intSubmittedCount = $intForReviewCount + $intVerifiedCount;

        $blnAlreadyConsolidated = MunicipalReport::query()
            ->where('mun_id', $objUser->mun_id)
            ->whereDate('mrp_period_start', $dtmMonth->toDateString())
            ->where('mrp_status', '!=', MunicipalReport::STATUS_RETURNED)
            ->whereDoesntHave('supersededBy')
            ->exists();

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.index', 'monthlyReports', 'Tourism Reports', [
            'rows' => $objRows,
            'month' => $dtmMonth,
            'monthOptions' => $this->recentMonthOptions(),
            'missingCount' => $intMissingCount,
            'forReviewCount' => $intForReviewCount,
            'verifiedCount' => $intVerifiedCount,
            'submittedCount' => $intSubmittedCount,
            'alreadyConsolidated' => $blnAlreadyConsolidated,
            'steps' => ReportWorkflowSteps::compute($intSubmittedCount, $intForReviewCount, $intVerifiedCount, $blnAlreadyConsolidated),
        ]);
    }

    /**
     * Manual Entry: the paper-report encoding form for one establishment +
     * month. Create-only — a month that already has a row (digital or
     * manual) is reviewed/verified instead, not re-encoded here.
     */
    public function showManualEntry(Request $objRequest, Listing $listing): View
    {
        $this->authorizeOwnMunicipality($objRequest, $listing);
        abort_unless($listing->requiresArrivalRecords(), 404);

        $dtmMonth = $this->resolvePeriod($objRequest);

        abort_if($listing->monthlyArrivalReports()->forPeriod($dtmMonth)->exists(), 422, 'This establishment already has a report for that month.');

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.manual-entry', 'monthlyReports', 'Manual Entry', [
            'listing' => $listing,
            'month' => $dtmMonth,
        ]);
    }

    public function storeManualEntry(Request $objRequest, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($objRequest, $listing);
        abort_unless($listing->requiresArrivalRecords(), 404);

        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'party_male' => ['required', 'integer', 'min:0'],
            'party_female' => ['required', 'integer', 'min:0'],
            'party_adults' => ['required', 'integer', 'min:0'],
            'party_children' => ['required', 'integer', 'min:0'],
            'party_seniors' => ['required', 'integer', 'min:0'],
            'party_local' => ['required', 'integer', 'min:0'],
            'party_foreign' => ['required', 'integer', 'min:0'],
        ]);

        $dtmMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();

        if ($listing->monthlyArrivalReports()->forPeriod($dtmMonth)->exists()) {
            return back()->with('toast', "{$listing->lst_name} already has a report for {$dtmMonth->format('F Y')}.")->with('toast_tone', 'danger');
        }

        try {
            $objReport = MonthlyArrivalReport::query()->create([
                'lst_id' => $listing->lst_id,
                'mun_id' => $listing->mun_id,
                'mar_period_month' => $dtmMonth->toDateString(),
                'mar_submission_source' => ReportSubmissionSource::ManualPaper,
                'mar_status' => MonthlyReportStatus::ForReview,
                'mar_party_male' => $arrData['party_male'],
                'mar_party_female' => $arrData['party_female'],
                'mar_party_adults' => $arrData['party_adults'],
                'mar_party_children' => $arrData['party_children'],
                'mar_party_seniors' => $arrData['party_seniors'],
                'mar_party_local' => $arrData['party_local'],
                'mar_party_foreign' => $arrData['party_foreign'],
                'mar_total_visitors' => $arrData['party_male'] + $arrData['party_female'],
                'mar_submitted_by' => $objRequest->user()->usr_id,
                'mar_submitted_at' => now(),
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to save the manual monthly arrival report.', ['exception' => $objException, 'lst_id' => $listing->lst_id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created(
            $objRequest->user(),
            'monthly_arrival_report',
            $objReport->mar_id,
            $listing->mun_id,
            $listing->lst_id,
            [
                'period_month' => $dtmMonth->toDateString(),
                'submission_source' => ReportSubmissionSource::ManualPaper->value,
                'total_visitors' => $objReport->mar_total_visitors,
            ],
        );

        return redirect()->route('lgu.monthlyReports.index', ['period' => $dtmMonth->format('Y-m')])
            ->with('toast', "{$dtmMonth->format('F Y')} report encoded for {$listing->lst_name}.");
    }

    /**
     * Review screen: totals, source/status, and — for Digital reports —
     * the underlying Arrival rows it was aggregated from, so the LGU can
     * spot-check before verifying. Also shows this report's full audit
     * trail (every create/verify/correction) and whether it's locked from
     * further correction (its MunicipalReport has already been Approved by
     * PTO — a provincial figure PTO has accepted shouldn't change
     * retroactively outside a fresh submission).
     */
    public function show(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless(
            $monthlyArrivalReport->mun_id !== null && $monthlyArrivalReport->mun_id === $objRequest->user()->mun_id,
            403
        );

        $monthlyArrivalReport->loadMissing(['listing', 'submitter', 'verifier', 'arrivals', 'municipalReport']);

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.show', 'monthlyReports', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'locked' => $this->isLocked($monthlyArrivalReport),
            'history' => $this->reportHistory($monthlyArrivalReport),
            'originBreakdown' => $monthlyArrivalReport->originBreakdown(),
        ]);
    }

    /**
     * Correction form — pre-filled with the report's current breakdown.
     * Available for both Digital and Manual/Paper reports: an establishment
     * that already submitted has no way to resubmit a month, so if its
     * figures were wrong, the LGU is the only one who can fix them (e.g.
     * after PTO returns the consolidated report for clarification).
     */
    public function edit(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless(
            $monthlyArrivalReport->mun_id !== null && $monthlyArrivalReport->mun_id === $objRequest->user()->mun_id,
            403
        );
        abort_if($this->isLocked($monthlyArrivalReport), 403, 'This report is part of a provincial report PTO has already approved and can no longer be corrected here.');

        $monthlyArrivalReport->loadMissing('listing');

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.edit', 'monthlyReports', 'Correct Report', [
            'report' => $monthlyArrivalReport,
        ]);
    }

    /**
     * Applies the correction and logs it (old values, new values, who,
     * when, and why — the reason is required). A report that was already
     * Verified reverts to ForReview, since a corrected report needs a
     * fresh verification rather than keeping a sign-off that predates the
     * correction.
     */
    public function update(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $monthlyArrivalReport->mun_id !== null && $monthlyArrivalReport->mun_id === $objRequest->user()->mun_id,
            403
        );
        abort_if($this->isLocked($monthlyArrivalReport), 403, 'This report is part of a provincial report PTO has already approved and can no longer be corrected here.');

        $arrData = $objRequest->validate([
            'party_male' => ['required', 'integer', 'min:0'],
            'party_female' => ['required', 'integer', 'min:0'],
            'party_adults' => ['required', 'integer', 'min:0'],
            'party_children' => ['required', 'integer', 'min:0'],
            'party_seniors' => ['required', 'integer', 'min:0'],
            'party_local' => ['required', 'integer', 'min:0'],
            'party_foreign' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $blnWasVerified = $monthlyArrivalReport->mar_status === MonthlyReportStatus::Verified;
        $arrBefore = $monthlyArrivalReport->getOriginal();

        try {
            $monthlyArrivalReport->update([
                'mar_party_male' => $arrData['party_male'],
                'mar_party_female' => $arrData['party_female'],
                'mar_party_adults' => $arrData['party_adults'],
                'mar_party_children' => $arrData['party_children'],
                'mar_party_seniors' => $arrData['party_seniors'],
                'mar_party_local' => $arrData['party_local'],
                'mar_party_foreign' => $arrData['party_foreign'],
                'mar_total_visitors' => $arrData['party_male'] + $arrData['party_female'],
                ...($blnWasVerified ? ['mar_status' => MonthlyReportStatus::ForReview, 'mar_verified_by' => null, 'mar_verified_at' => null] : []),
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to correct the monthly arrival report.', ['exception' => $objException, 'mar_id' => $monthlyArrivalReport->mar_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated(
            $objRequest->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $monthlyArrivalReport->mun_id,
            $monthlyArrivalReport->lst_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
            $arrData['reason'],
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport)
            ->with('toast', $blnWasVerified ? 'Report corrected — re-verify before consolidating again.' : 'Report corrected.');
    }

    public function verify(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $monthlyArrivalReport->mun_id !== null && $monthlyArrivalReport->mun_id === $objRequest->user()->mun_id,
            403
        );
        abort_if($monthlyArrivalReport->mar_status === MonthlyReportStatus::Verified, 403, 'This report has already been verified.');

        $arrBefore = $monthlyArrivalReport->getOriginal();

        try {
            $monthlyArrivalReport->update([
                'mar_status' => MonthlyReportStatus::Verified,
                'mar_verified_by' => $objRequest->user()->usr_id,
                'mar_verified_at' => now(),
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to verify the monthly arrival report.', ['exception' => $objException, 'mar_id' => $monthlyArrivalReport->mar_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::validated(
            $objRequest->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $monthlyArrivalReport->mun_id,
            $monthlyArrivalReport->lst_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
        );

        $monthlyArrivalReport->loadMissing('listing');
        if ($monthlyArrivalReport->listing) {
            Notifier::toEstablishment(
                $monthlyArrivalReport->listing,
                'monthly-report-verified',
                "Your {$monthlyArrivalReport->mar_period_month->format('F Y')} monthly report was verified by your LGU and is being sent to the PTO.",
                route('establishment.arrivals.monthly'),
                'ti-circle-check',
            );
        }

        return back()->with('toast', 'Report verified.');
    }

    /**
     * Sums every Verified report for the municipality+period into a
     * MunicipalReport and submits it to PTO (status SUBMITTED) — this one
     * action covers both "consolidate" and "submit to PTO", since there is
     * no separate handoff step. Only Verified reports ever count toward the
     * total; a month with none yet cannot be consolidated.
     */
    public function consolidate(Request $objRequest): RedirectResponse
    {
        $objUser = $objRequest->user();
        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);
        $dtmMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();

        $objVerifiedReports = MonthlyArrivalReport::query()
            ->visibleTo($objUser)
            ->forPeriod($dtmMonth)
            ->where('mar_status', MonthlyReportStatus::Verified)
            ->get();

        if ($objVerifiedReports->isEmpty()) {
            return back()->with('toast', "No verified reports for {$dtmMonth->format('F Y')} yet — nothing to consolidate.")->with('toast_tone', 'danger');
        }

        $objExisting = MunicipalReport::query()
            ->where('mun_id', $objUser->mun_id)
            ->whereDate('mrp_period_start', $dtmMonth->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();

        // Pending PTO review can't be resubmitted out from under it, but a
        // Verified (APPROVED) report CAN be reopened by a fresh LGU
        // resubmission — it just goes back to For Review and is logged as a
        // reopen, not an ordinary consolidation.
        abort_if(
            $objExisting && in_array($objExisting->mrp_status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
            403,
            'This municipality already has a report pending PTO review for that month.'
        );
        $blnWasVerified = $objExisting?->isFrozen() ?? false;

        $intTotal = (int) $objVerifiedReports->sum('mar_total_visitors');

        try {
            $objMunicipalReport = DB::transaction(function () use ($objExisting, $blnWasVerified, $objUser, $dtmMonth, $intTotal, $objVerifiedReports) {
                // A Verified report's own row is frozen forever (its
                // mrp_frozen_snapshot, PDF, and verification code must never
                // change) — reopening it creates a brand-new revision row
                // instead of overwriting it. Returning a RETURNED report (or
                // consolidating for the first time) still updates/creates in
                // place as before, since neither of those is frozen.
                $objMunicipalReport = match (true) {
                    $blnWasVerified => MunicipalReport::query()->create([
                        'mrp_municipality' => $objExisting->mrp_municipality,
                        'mun_id' => $objExisting->mun_id,
                        'mrp_submitted_by' => $objUser->usr_id,
                        'mrp_period_start' => $dtmMonth->toDateString(),
                        'mrp_period_end' => $dtmMonth->endOfMonth()->toDateString(),
                        'mrp_total_arrivals' => $intTotal,
                        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
                        'mrp_revision_number' => $objExisting->mrp_revision_number + 1,
                        'mrp_supersedes_id' => $objExisting->mrp_id,
                    ]),
                    $objExisting !== null => tap($objExisting)->update([
                        'mrp_total_arrivals' => $intTotal,
                        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
                        'mrp_submitted_by' => $objUser->usr_id,
                        'mrp_reviewed_by' => null,
                        'mrp_reviewed_at' => null,
                    ]),
                    default => MunicipalReport::query()->create([
                        'mrp_municipality' => $objUser->usr_organization_subtitle,
                        'mun_id' => $objUser->mun_id,
                        'mrp_submitted_by' => $objUser->usr_id,
                        'mrp_period_start' => $dtmMonth->toDateString(),
                        'mrp_period_end' => $dtmMonth->endOfMonth()->toDateString(),
                        'mrp_total_arrivals' => $intTotal,
                        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
                    ]),
                };

                $objVerifiedReports->each->update(['mrp_id' => $objMunicipalReport->mrp_id]);

                return $objMunicipalReport;
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to consolidate the municipal report.', ['exception' => $objException, 'mun_id' => $objUser->mun_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $arrNewValues = [
            'period_month' => $dtmMonth->toDateString(),
            'total_arrivals' => $intTotal,
            'source_report_count' => $objVerifiedReports->count(),
        ];

        if ($blnWasVerified) {
            OperationLogger::reopened($objUser, 'municipal_report', $objMunicipalReport->mrp_id, $objUser->mun_id, $arrNewValues);
        } else {
            OperationLogger::consolidated($objUser, 'municipal_report', $objMunicipalReport->mrp_id, $objUser->mun_id, $arrNewValues);
        }

        Notifier::toPto(
            'municipal-report-submitted',
            "{$objMunicipalReport->mrp_municipality} submitted its {$dtmMonth->format('F Y')} tourism report for review.",
            route('pto.municipalReports.show', $objMunicipalReport),
            'ti-report-analytics',
        );

        return redirect()->route('lgu.monthlyReports.index', ['period' => $dtmMonth->format('Y-m')])
            ->with('toast', "{$dtmMonth->format('F Y')} report consolidated and submitted to PTO.");
    }

    /**
     * A report can't be corrected while it's part of a MunicipalReport PTO
     * hasn't returned — editing it would silently make that MunicipalReport's
     * total_arrivals stale (SUBMITTED, awaiting PTO) or retroactively change
     * a figure PTO already accepted (APPROVED). Once PTO returns it, or if
     * it was never consolidated at all, editing is fine.
     */
    private function isLocked(MonthlyArrivalReport $objReport): bool
    {
        return $objReport->municipalReport !== null && $objReport->municipalReport->mrp_status !== MunicipalReport::STATUS_RETURNED;
    }

    private function resolvePeriod(Request $objRequest): CarbonImmutable
    {
        $strPeriod = $objRequest->query('period');

        if (is_string($strPeriod) && preg_match('/^\d{4}-\d{2}$/', $strPeriod)) {
            return CarbonImmutable::createFromFormat('Y-m', $strPeriod)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonthOptions(): Collection
    {
        return collect(range(0, 11))
            ->map(fn (int $intIndex) => CarbonImmutable::now()->subMonthsNoOverflow($intIndex)->startOfMonth());
    }
}
