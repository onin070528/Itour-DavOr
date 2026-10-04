<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide, read-only view of the same establishment × month
 * reporting status Lgu\MonthlyReportsController shows, switchable across
 * any municipality — encoding, reviewing, verifying, and consolidating
 * stay LGU-only actions; PTO only watches progress and drills into detail.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\MonthlyReportStatus;
use App\Exports\OfficialReportExport;
use App\Http\Controllers\Concerns\TracksReportHistory;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Support\OfficialReportBuilder;
use App\Support\OperationLogger;
use App\Support\ReportWorkflowSteps;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MonthlyReportsController extends PtoController
{
    use TracksReportHistory;

    public function index(Request $request): View
    {
        $municipalities = Municipality::query()->orderBy('name')->get();
        $month = $this->resolvePeriod($request);
        $municipality = $this->resolveMunicipality($request, $municipalities);

        $establishments = Listing::query()
            ->where('municipality_id', $municipality?->id)
            ->where('category', '!=', 'destinations')
            ->orderBy('name')
            ->get();

        $reportsByListing = MonthlyArrivalReport::query()
            ->where('municipality_id', $municipality?->id)
            ->forPeriod($month)
            ->with(['submitter', 'verifier'])
            ->get()
            ->keyBy('listing_id');

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

        $alreadyConsolidated = $municipality && MunicipalReport::query()
            ->where('municipality_id', $municipality->id)
            ->whereDate('period_start', $month->toDateString())
            ->where('status', '!=', MunicipalReport::STATUS_RETURNED)
            ->whereDoesntHave('supersededBy')
            ->exists();

        return $this->renderPto($request, 'pto.monthly-reports.index', 'monthlyReports', 'Provincial Reports', [
            'rows' => $rows,
            'month' => $month,
            'monthOptions' => $this->recentMonthOptions(),
            'municipalities' => $municipalities,
            'municipality' => $municipality,
            'missingCount' => $missingCount,
            'forReviewCount' => $forReviewCount,
            'verifiedCount' => $verifiedCount,
            'submittedCount' => $submittedCount,
            'alreadyConsolidated' => $alreadyConsolidated,
            'steps' => ReportWorkflowSteps::compute($submittedCount, $forReviewCount, $verifiedCount, $alreadyConsolidated),
        ]);
    }

    /**
     * Read-only mirror of Lgu\MonthlyReportsController::show() — same
     * detail, no Verify action.
     */
    public function show(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        $monthlyArrivalReport->loadMissing(['listing', 'submitter', 'verifier', 'arrivals']);

        return $this->renderPto($request, 'pto.monthly-reports.show', 'monthlyReports', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'history' => $this->reportHistory($monthlyArrivalReport),
        ]);
    }

    /**
     * Official Report (Provincial Reports). If the municipality already has
     * a Verified consolidated report for this period, that one IS the
     * province-ready document — redirect to it (same template, same
     * verification code, same frozen snapshot — see
     * Pto\MunicipalReportsController) instead of building a second,
     * divergent one. Otherwise renders a live Draft straight from
     * establishment-level data, useful before the LGU has consolidated yet.
     */
    public function officialReport(Request $request): View|RedirectResponse
    {
        $municipality = $this->resolveMunicipality($request, Municipality::query()->orderBy('name')->get());
        abort_if(! $municipality, 404, 'Select a municipality first.');
        $month = $this->resolvePeriod($request);

        $municipalReport = $this->currentMunicipalReport($municipality, $month);
        if ($municipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport', $municipalReport);
        }

        $data = $this->officialReportData($municipality, $month);

        OperationLogger::exported($request->user(), 'provincial_report', $municipality->id, ['action' => 'preview', 'period' => $month->toDateString()]);

        return view('pdf.official-report', [
            'report' => $data,
            'preview' => true,
            'pdfUrl' => route('pto.monthlyReports.officialReport.pdf', ['period' => $month->format('Y-m'), 'municipality_id' => $municipality->id]),
            'excelUrl' => route('pto.monthlyReports.officialReport.excel', ['period' => $month->format('Y-m'), 'municipality_id' => $municipality->id]),
        ]);
    }

    public function officialReportPdf(Request $request): Response|RedirectResponse
    {
        $municipality = $this->resolveMunicipality($request, Municipality::query()->orderBy('name')->get());
        abort_if(! $municipality, 404, 'Select a municipality first.');
        $month = $this->resolvePeriod($request);

        $municipalReport = $this->currentMunicipalReport($municipality, $month);
        if ($municipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport.pdf', $municipalReport);
        }

        $data = $this->officialReportData($municipality, $month);
        $this->abortIfUnbalanced($data);

        OperationLogger::exported($request->user(), 'provincial_report', $municipality->id, ['action' => 'download_pdf', 'period' => $month->toDateString()]);

        return Pdf::loadView('pdf.official-report', ['report' => $data, 'preview' => false])
            ->setPaper('a4')
            ->download("{$data['reference_number']}.pdf");
    }

    public function officialReportExcel(Request $request)
    {
        $municipality = $this->resolveMunicipality($request, Municipality::query()->orderBy('name')->get());
        abort_if(! $municipality, 404, 'Select a municipality first.');
        $month = $this->resolvePeriod($request);

        $municipalReport = $this->currentMunicipalReport($municipality, $month);
        if ($municipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport.excel', $municipalReport);
        }

        $data = $this->officialReportData($municipality, $month);
        $this->abortIfUnbalanced($data);

        OperationLogger::exported($request->user(), 'provincial_report', $municipality->id, ['action' => 'export_excel', 'period' => $month->toDateString()]);

        return Excel::download(new OfficialReportExport($data), "{$data['reference_number']}.xlsx");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function abortIfUnbalanced(array $data): void
    {
        $rows = $data['groups']->flatMap(fn (array $group) => $group['rows']);
        $errors = OfficialReportBuilder::validateColumnSums($rows);

        abort_if($errors !== [], 422, 'This report cannot be generated until its Male/Female, Adults/Children/Seniors, and Local/Foreign columns all add up to the Total for every establishment: '.implode(' ', $errors));
    }

    private function currentMunicipalReport(Municipality $municipality, CarbonImmutable $month): ?MunicipalReport
    {
        return MunicipalReport::query()
            ->with('reviewer')
            ->where('municipality_id', $municipality->id)
            ->whereDate('period_start', $month->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();
    }

    /**
     * Builds the generic shape resources/views/pdf/official-report.blade.php
     * renders, straight from establishment-level data — see
     * App\Support\OfficialReportBuilder. Always a live Draft: this method is
     * only ever reached when no Verified MunicipalReport exists yet for the
     * period (officialReport()/Pdf()/Excel() redirect to that one instead).
     *
     * @return array<string, mixed>
     */
    private function officialReportData(Municipality $municipality, CarbonImmutable $month): array
    {
        $establishments = Listing::query()
            ->with('categoryRecord')
            ->where('municipality_id', $municipality->id)
            ->get()
            ->filter(fn (Listing $listing) => $listing->isQrEnabled());

        $reportsByListing = MonthlyArrivalReport::query()
            ->where('municipality_id', $municipality->id)
            ->forPeriod($month)
            ->with('submitter')
            ->get()
            ->keyBy('listing_id');

        $reported = $establishments->filter(fn (Listing $listing) => $reportsByListing->has($listing->id));

        $rows = $reported->map(function (Listing $listing) use ($reportsByListing) {
            $monthlyReport = $reportsByListing->get($listing->id);

            return [
                'establishment' => $listing->name,
                'category' => $listing->categoryRecord?->cat_name ?? 'Uncategorized',
                'source' => $monthlyReport->submission_source->label(),
                'male' => (int) $monthlyReport->party_male,
                'female' => (int) $monthlyReport->party_female,
                'total' => (int) $monthlyReport->total_visitors,
                'adults' => (int) $monthlyReport->party_adults,
                'children' => (int) $monthlyReport->party_children,
                'seniors' => (int) $monthlyReport->party_seniors,
                'local' => (int) $monthlyReport->party_local,
                'foreign' => (int) $monthlyReport->party_foreign,
            ];
        })->values();

        $digitalCount = $reported->filter(fn (Listing $listing) => $reportsByListing->get($listing->id)->submission_source->value === 'digital')->count();
        $paperCount = $reported->count() - $digitalCount;

        return [
            'title' => 'Provincial Tourism Report',
            'letterhead' => [
                'office_name' => 'Provincial Tourism Office',
                'office_subtitle' => 'Province of Davao Oriental — '.$municipality->name,
                'address' => 'Capitol Compound, Brgy. Dahican, City of Mati, Davao Oriental',
            ],
            'period_label' => $month->format('F Y'),
            'reference_number' => sprintf('PTR-%03d-%s', $municipality->id, $month->format('Ym')),
            'status_label' => 'Draft',
            'is_draft' => true,
            'verified_label' => null,
            'revision_number' => 1,
            'supersedes_reference' => null,
            'submission_summary' => "{$reported->count()} of {$establishments->count()} establishments reported ({$digitalCount} digital, {$paperCount} paper)",
            'groups' => OfficialReportBuilder::groupByCategory($rows),
            'grand_total' => OfficialReportBuilder::sumRows($rows),
            'remarks' => 'Preliminary — not yet consolidated or verified for this period.',
            'signatures' => [
                'prepared_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
                'reviewed_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
                'approved_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
            ],
            'verification_code' => null,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    }

    private function resolveMunicipality(Request $request, Collection $municipalities): ?Municipality
    {
        $id = $request->query('municipality_id');

        if ($id && $match = $municipalities->firstWhere('id', (int) $id)) {
            return $match;
        }

        return $municipalities->first();
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
