{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Quick-view modal for an establishment report on the LGU Monthly
    Reports page (View on Verified / For Correction rows), so the LGU can
    check it without leaving the table. Verifying, returning, and
    correcting stay on the full review page ("Open Full Report").
    Expects $report (MonthlyArrivalReport with submitter, verifier loaded), $listing.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-dashboard.modal :id="'lgu-report-modal-'.$report->mar_id" :title="$listing->lst_name.' — '.$report->mar_period_month->format('F Y')" max-width="max-w-2xl">
    @if ($report->mar_status === \App\Enums\MonthlyReportStatus::ForCorrection)
        <div class="mb-4 rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="font-semibold">Returned to the establishment for correction</p>
            <p class="mt-1">Remarks: {{ $report->mar_remarks ?: '—' }}</p>
        </div>
    @endif

    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt>
            <dd class="mt-1"><x-dashboard.status-badge :tone="$report->mar_status->badgeTone()">{{ $report->mar_status->label() }}</x-dashboard.status-badge></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Tourist Arrivals</dt>
            <dd class="mt-1 text-lg font-bold text-sand-900">{{ number_format($report->mar_total_visitors) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Source</dt>
            <dd class="mt-1 text-sand-800">{{ $report->mar_submission_source->label() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">{{ $report->mar_submission_source === \App\Enums\ReportSubmissionSource::ManualPaper ? 'Encoded' : 'Submitted' }}</dt>
            <dd class="mt-1 text-sand-800">{{ $report->mar_submitted_at ? ($report->submitter->usr_name ?? '—').' · '.$report->mar_submitted_at->format('M j, Y') : 'Not yet' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Verified</dt>
            <dd class="mt-1 text-sand-800">{{ $report->mar_verified_at ? ($report->verifier->usr_name ?? '—').' · '.$report->mar_verified_at->format('M j, Y') : 'Not yet' }}</dd>
        </div>
    </dl>

    <h3 class="mt-5 text-xs font-semibold text-sand-500 uppercase">Visitor Classifications</h3>
    <dl class="mt-2 grid grid-cols-4 gap-3 rounded-md border border-sand-200 p-3 text-sm sm:grid-cols-7">
        @foreach (['Male' => 'mar_party_male', 'Female' => 'mar_party_female', 'Adults' => 'mar_party_adults', 'Children' => 'mar_party_children', 'Seniors' => 'mar_party_seniors', 'Local' => 'mar_party_local', 'Foreign' => 'mar_party_foreign'] as $label => $column)
            <div>
                <dt class="text-xs text-sand-500">{{ $label }}</dt>
                <dd class="font-semibold text-sand-900">{{ number_format($report->{$column}) }}</dd>
            </div>
        @endforeach
    </dl>

    <x-slot:footer>
        <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Close</button>
        <a href="{{ route('lgu.monthlyReports.show', [$report, 'view' => 'a4']) }}" class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Report Preview</a>
        <a href="{{ route('lgu.monthlyReports.show', $report) }}" class="rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">Open Full Report</a>
    </x-slot:footer>
</x-dashboard.modal>
