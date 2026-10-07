{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Quick-view modal for one month on the LGU Municipal Reports
    Monthly Records table (View on months that are not ready to submit):
    status, verified total, coverage, pending reviews, PTO's remarks, and
    submission details — without leaving the page. Submitting to PTO and
    the full establishment breakdown stay on the full report page.
    Expects $entry (one Lgu\MonthlyReportsController::municipalIndex() month).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $period = $entry['month']->format('Y-m');
    $municipalReport = $entry['municipalReport'];
@endphp

<x-dashboard.modal :id="'municipal-modal-'.$period" :title="'Municipal Report — '.$entry['month']->format('F Y')" max-width="max-w-2xl">
    @if ($municipalReport?->mrp_status === \App\Models\MunicipalReport::STATUS_RETURNED)
        <div class="mb-4 rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="font-semibold">Returned by PTO for clarification</p>
            <p class="mt-1">Remarks: {{ $municipalReport->mrp_remarks ?: '—' }}</p>
        </div>
    @endif

    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt>
            <dd class="mt-1"><x-dashboard.status-badge :tone="$entry['statusTone']">{{ $entry['status'] }}</x-dashboard.status-badge></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Verified Arrivals</dt>
            <dd class="mt-1 text-lg font-bold text-sand-900">{{ $entry['verifiedCount'] > 0 ? number_format($entry['verifiedTotal']) : '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Completeness</dt>
            <dd class="mt-1 text-sand-800">{{ $entry['verifiedCount'] }} of {{ $entry['establishmentCount'] }} establishments verified</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Waiting for Review</dt>
            <dd class="mt-1 text-sand-800">{{ $entry['pendingCount'] }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Not Submitted</dt>
            <dd class="mt-1 text-sand-800">{{ $entry['notSubmittedCount'] }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted to PTO</dt>
            <dd class="mt-1 text-sand-800">{{ $municipalReport ? ($municipalReport->submitter->usr_name ?? '—').' · '.$municipalReport->mrp_updated_at->format('M j, Y') : 'Not yet' }}</dd>
        </div>
    </dl>

    @if ($entry['pendingCount'] > 0)
        <p class="mt-4 text-sm text-sand-600">{{ $entry['pendingCount'] }} establishment report(s) still need your review under Monthly Reports before this month can be sent to PTO.</p>
    @endif

    <x-slot:footer>
        <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Close</button>
        @if ($entry['pendingCount'] > 0)
            <a href="{{ route('lgu.monthlyReports.index', ['period' => $period]) }}" class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Go to Monthly Reports</a>
        @endif
        <a href="{{ route('lgu.monthlyReports.municipal.show', $period) }}" class="rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">Open Full Report</a>
    </x-slot:footer>
</x-dashboard.modal>
