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
use App\Support\OperationLogger;
use App\Support\ReportWorkflowSteps;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = $this->resolvePeriod($request);

        $establishments = Listing::query()
            ->visibleTo($user)
            ->where('category', '!=', 'destinations')
            ->orderBy('name')
            ->get();

        $reportsByListing = MonthlyArrivalReport::query()
            ->visibleTo($user)
            ->forPeriod($month)
            ->with(['submitter', 'verifier'])
            ->get()
            ->keyBy('listing_id');

        // "Not Submitted" rows first, then For Review, then Verified — the
        // ones needing LGU attention surface at the top of the table.
        $statusPriority = ['Not Submitted' => 0, 'For Review' => 1, 'Verified' => 2];

        $rows = $establishments->map(function (Listing $listing) use ($reportsByListing) {
            $report = $reportsByListing->get($listing->id);

            return [
                'listing' => $listing,
                'report' => $report,
                'status' => $report?->status->label() ?? 'Not Submitted',
            ];
        })->sortBy(fn (array $row) => $statusPriority[$row['status']])->values();

        $missingCount = $rows->whereNull('report')->count();
        $forReviewCount = $rows->filter(fn (array $row) => $row['report']?->status === MonthlyReportStatus::ForReview)->count();
        $verifiedCount = $rows->filter(fn (array $row) => $row['report']?->status === MonthlyReportStatus::Verified)->count();
        $submittedCount = $forReviewCount + $verifiedCount;

        $alreadyConsolidated = MunicipalReport::query()
            ->where('municipality_id', $user->municipality_id)
            ->whereDate('period_start', $month->toDateString())
            ->where('status', '!=', MunicipalReport::STATUS_RETURNED)
            ->whereDoesntHave('supersededBy')
            ->exists();

        return $this->renderLgu($request, 'lgu.monthly-reports.index', 'monthlyReports', 'Tourism Reports', [
            'rows' => $rows,
            'month' => $month,
            'monthOptions' => $this->recentMonthOptions(),
            'missingCount' => $missingCount,
            'forReviewCount' => $forReviewCount,
            'verifiedCount' => $verifiedCount,
            'submittedCount' => $submittedCount,
            'alreadyConsolidated' => $alreadyConsolidated,
            'steps' => ReportWorkflowSteps::compute($submittedCount, $forReviewCount, $verifiedCount, $alreadyConsolidated),
        ]);
    }

    /**
     * Manual Entry: the paper-report encoding form for one establishment +
     * month. Create-only — a month that already has a row (digital or
     * manual) is reviewed/verified instead, not re-encoded here.
     */
    public function showManualEntry(Request $request, Listing $listing): View
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category === 'destinations', 404);

        $month = $this->resolvePeriod($request);

        abort_if($listing->monthlyArrivalReports()->forPeriod($month)->exists(), 422, 'This establishment already has a report for that month.');

        return $this->renderLgu($request, 'lgu.monthly-reports.manual-entry', 'monthlyReports', 'Manual Entry', [
            'listing' => $listing,
            'month' => $month,
        ]);
    }

    public function storeManualEntry(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category === 'destinations', 404);

        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'party_male' => ['required', 'integer', 'min:0'],
            'party_female' => ['required', 'integer', 'min:0'],
            'party_adults' => ['required', 'integer', 'min:0'],
            'party_children' => ['required', 'integer', 'min:0'],
            'party_seniors' => ['required', 'integer', 'min:0'],
            'party_local' => ['required', 'integer', 'min:0'],
            'party_foreign' => ['required', 'integer', 'min:0'],
        ]);

        $month = CarbonImmutable::createFromFormat('Y-m', $data['period_month'])->startOfMonth();

        if ($listing->monthlyArrivalReports()->forPeriod($month)->exists()) {
            return back()->with('toast', "{$listing->name} already has a report for {$month->format('F Y')}.")->with('toast_tone', 'danger');
        }

        $report = MonthlyArrivalReport::query()->create([
            'listing_id' => $listing->id,
            'municipality_id' => $listing->municipality_id,
            'period_month' => $month->toDateString(),
            'submission_source' => ReportSubmissionSource::ManualPaper,
            'status' => MonthlyReportStatus::ForReview,
            'party_male' => $data['party_male'],
            'party_female' => $data['party_female'],
            'party_adults' => $data['party_adults'],
            'party_children' => $data['party_children'],
            'party_seniors' => $data['party_seniors'],
            'party_local' => $data['party_local'],
            'party_foreign' => $data['party_foreign'],
            'total_visitors' => $data['party_male'] + $data['party_female'],
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        OperationLogger::created(
            $request->user(),
            'monthly_arrival_report',
            $report->id,
            $listing->municipality_id,
            $listing->id,
            [
                'period_month' => $month->toDateString(),
                'submission_source' => ReportSubmissionSource::ManualPaper->value,
                'total_visitors' => $report->total_visitors,
            ],
        );

        return redirect()->route('lgu.monthlyReports.index', ['period' => $month->format('Y-m')])
            ->with('toast', "{$month->format('F Y')} report encoded for {$listing->name}.");
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
    public function show(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless(
            $monthlyArrivalReport->municipality_id !== null && $monthlyArrivalReport->municipality_id === $request->user()->municipality_id,
            403
        );

        $monthlyArrivalReport->loadMissing(['listing', 'submitter', 'verifier', 'arrivals', 'municipalReport']);

        return $this->renderLgu($request, 'lgu.monthly-reports.show', 'monthlyReports', 'Monthly Report', [
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
    public function edit(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless(
            $monthlyArrivalReport->municipality_id !== null && $monthlyArrivalReport->municipality_id === $request->user()->municipality_id,
            403
        );
        abort_if($this->isLocked($monthlyArrivalReport), 403, 'This report is part of a provincial report PTO has already approved and can no longer be corrected here.');

        $monthlyArrivalReport->loadMissing('listing');

        return $this->renderLgu($request, 'lgu.monthly-reports.edit', 'monthlyReports', 'Correct Report', [
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
    public function update(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $monthlyArrivalReport->municipality_id !== null && $monthlyArrivalReport->municipality_id === $request->user()->municipality_id,
            403
        );
        abort_if($this->isLocked($monthlyArrivalReport), 403, 'This report is part of a provincial report PTO has already approved and can no longer be corrected here.');

        $data = $request->validate([
            'party_male' => ['required', 'integer', 'min:0'],
            'party_female' => ['required', 'integer', 'min:0'],
            'party_adults' => ['required', 'integer', 'min:0'],
            'party_children' => ['required', 'integer', 'min:0'],
            'party_seniors' => ['required', 'integer', 'min:0'],
            'party_local' => ['required', 'integer', 'min:0'],
            'party_foreign' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $wasVerified = $monthlyArrivalReport->status === MonthlyReportStatus::Verified;
        $before = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'party_male' => $data['party_male'],
            'party_female' => $data['party_female'],
            'party_adults' => $data['party_adults'],
            'party_children' => $data['party_children'],
            'party_seniors' => $data['party_seniors'],
            'party_local' => $data['party_local'],
            'party_foreign' => $data['party_foreign'],
            'total_visitors' => $data['party_male'] + $data['party_female'],
            ...($wasVerified ? ['status' => MonthlyReportStatus::ForReview, 'verified_by' => null, 'verified_at' => null] : []),
        ]);

        OperationLogger::updated(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $monthlyArrivalReport->municipality_id,
            $monthlyArrivalReport->listing_id,
            OperationLogger::diff($before, $monthlyArrivalReport),
            $data['reason'],
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport)
            ->with('toast', $wasVerified ? 'Report corrected — re-verify before consolidating again.' : 'Report corrected.');
    }

    public function verify(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $monthlyArrivalReport->municipality_id !== null && $monthlyArrivalReport->municipality_id === $request->user()->municipality_id,
            403
        );
        abort_if($monthlyArrivalReport->status === MonthlyReportStatus::Verified, 403, 'This report has already been verified.');

        $before = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'status' => MonthlyReportStatus::Verified,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        OperationLogger::validated(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $monthlyArrivalReport->municipality_id,
            $monthlyArrivalReport->listing_id,
            OperationLogger::diff($before, $monthlyArrivalReport),
        );

        return back()->with('toast', 'Report verified.');
    }

    /**
     * Sums every Verified report for the municipality+period into a
     * MunicipalReport and submits it to PTO (status SUBMITTED) — this one
     * action covers both "consolidate" and "submit to PTO", since there is
     * no separate handoff step. Only Verified reports ever count toward the
     * total; a month with none yet cannot be consolidated.
     */
    public function consolidate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);
        $month = CarbonImmutable::createFromFormat('Y-m', $data['period_month'])->startOfMonth();

        $verifiedReports = MonthlyArrivalReport::query()
            ->visibleTo($user)
            ->forPeriod($month)
            ->where('status', MonthlyReportStatus::Verified)
            ->get();

        if ($verifiedReports->isEmpty()) {
            return back()->with('toast', "No verified reports for {$month->format('F Y')} yet — nothing to consolidate.")->with('toast_tone', 'danger');
        }

        $existing = MunicipalReport::query()
            ->where('municipality_id', $user->municipality_id)
            ->whereDate('period_start', $month->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();

        // Pending PTO review can't be resubmitted out from under it, but a
        // Verified (APPROVED) report CAN be reopened by a fresh LGU
        // resubmission — it just goes back to For Review and is logged as a
        // reopen, not an ordinary consolidation.
        abort_if(
            $existing && in_array($existing->status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
            403,
            'This municipality already has a report pending PTO review for that month.'
        );
        $wasVerified = $existing?->isFrozen() ?? false;

        $total = (int) $verifiedReports->sum('total_visitors');

        $municipalReport = DB::transaction(function () use ($existing, $wasVerified, $user, $month, $total, $verifiedReports) {
            // A Verified report's own row is frozen forever (its
            // frozen_snapshot, PDF, and verification code must never
            // change) — reopening it creates a brand-new revision row
            // instead of overwriting it. Returning a RETURNED report (or
            // consolidating for the first time) still updates/creates in
            // place as before, since neither of those is frozen.
            $municipalReport = match (true) {
                $wasVerified => MunicipalReport::query()->create([
                    'municipality' => $existing->municipality,
                    'municipality_id' => $existing->municipality_id,
                    'submitted_by' => $user->id,
                    'period_start' => $month->toDateString(),
                    'period_end' => $month->endOfMonth()->toDateString(),
                    'total_arrivals' => $total,
                    'status' => MunicipalReport::STATUS_SUBMITTED,
                    'revision_number' => $existing->revision_number + 1,
                    'supersedes_id' => $existing->id,
                ]),
                $existing !== null => tap($existing)->update([
                    'total_arrivals' => $total,
                    'status' => MunicipalReport::STATUS_SUBMITTED,
                    'submitted_by' => $user->id,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]),
                default => MunicipalReport::query()->create([
                    'municipality' => $user->organization_subtitle,
                    'municipality_id' => $user->municipality_id,
                    'submitted_by' => $user->id,
                    'period_start' => $month->toDateString(),
                    'period_end' => $month->endOfMonth()->toDateString(),
                    'total_arrivals' => $total,
                    'status' => MunicipalReport::STATUS_SUBMITTED,
                ]),
            };

            $verifiedReports->each->update(['municipal_report_id' => $municipalReport->id]);

            return $municipalReport;
        });

        $newValues = [
            'period_month' => $month->toDateString(),
            'total_arrivals' => $total,
            'source_report_count' => $verifiedReports->count(),
        ];

        if ($wasVerified) {
            OperationLogger::reopened($user, 'municipal_report', $municipalReport->id, $user->municipality_id, $newValues);
        } else {
            OperationLogger::consolidated($user, 'municipal_report', $municipalReport->id, $user->municipality_id, $newValues);
        }

        return redirect()->route('lgu.monthlyReports.index', ['period' => $month->format('Y-m')])
            ->with('toast', "{$month->format('F Y')} report consolidated and submitted to PTO.");
    }

    /**
     * A report can't be corrected while it's part of a MunicipalReport PTO
     * hasn't returned — editing it would silently make that MunicipalReport's
     * total_arrivals stale (SUBMITTED, awaiting PTO) or retroactively change
     * a figure PTO already accepted (APPROVED). Once PTO returns it, or if
     * it was never consolidated at all, editing is fine.
     */
    private function isLocked(MonthlyArrivalReport $report): bool
    {
        return $report->municipalReport !== null && $report->municipalReport->status !== MunicipalReport::STATUS_RETURNED;
    }

    private function resolvePeriod(Request $request): CarbonImmutable
    {
        $period = $request->query('period');

        if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonthOptions(): Collection
    {
        return collect(range(0, 11))
            ->map(fn (int $i) => CarbonImmutable::now()->subMonthsNoOverflow($i)->startOfMonth());
    }
}
