{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: View Municipal Report — the LGU's own consolidated report for
    one month (View -> Report Preview -> Review -> Submit to PTO). Shows every
    establishment's status, which Verified reports make up the municipal
    total (computed by the system, never typed), problems PTO would reject,
    PTO's return remarks, and the report's submission history.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $period = $month->format('Y-m');
    $municipalReport = $summary['municipalReport'];
    $isReturned = $municipalReport?->mrp_status === \App\Models\MunicipalReport::STATUS_RETURNED;
    $statusTone = fn (string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        'Submitted' => 'info',
        'Draft' => 'neutral',
        default => 'danger',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="'Municipal Report — '.$month->format('F Y')"
        description="{{ $municipality }}'s consolidated monthly tourism report, generated from verified establishment reports."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.monthlyReports.municipal', ['year' => $month->year, 'section' => 'records']) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Monthly Records
            </a>
            @if ($summary['verifiedCount'] > 0 || $municipalReport)
                <a href="{{ route('lgu.monthlyReports.municipal.preview', $period) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-file-description" aria-hidden="true"></i>
                    Report Preview
                </a>
                <a href="{{ route('lgu.monthlyReports.municipal.pdf', $period) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-download" aria-hidden="true"></i>
                    Download PDF
                </a>
            @endif
            @if ($summary['canSubmit'])
                <form method="POST" action="{{ route('lgu.monthlyReports.consolidate') }}">
                    @csrf
                    <input type="hidden" name="period_month" value="{{ $period }}">
                    <button
                        type="button"
                        data-confirm-trigger
                        data-confirm-title="Submit this municipal report to PTO?"
                        data-confirm-message="This sends {{ $municipality }}'s {{ $month->format('F Y') }} report ({{ number_format($summary['verifiedTotal']) }} verified arrivals from {{ $summary['verifiedCount'] }} establishment report(s)) to PTO. Make sure you have checked the Report Preview."
                        data-confirm-label="{{ $isReturned ? 'Resubmit to PTO' : 'Submit to PTO' }}"
                        class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                    >
                        <i class="ti ti-send" aria-hidden="true"></i>
                        {{ $isReturned ? 'Resubmit to PTO' : 'Submit to PTO' }}
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-dashboard.page-header>

    {{-- Where this report stands, and what to do next. --}}
    @if ($isReturned)
        <div class="mt-6 rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-arrow-back-up" aria-hidden="true"></i> Returned by PTO for clarification</p>
            <p class="mt-1">Remarks: {{ $municipalReport->mrp_remarks ?: '—' }}</p>
            <p class="mt-1 text-xs">Correct the affected establishment reports under Monthly Reports (each correction needs re-verification), then come back here and resubmit.</p>
        </div>
    @elseif ($summary['isSentToPto'])
        <div class="mt-6 flex items-center gap-2 rounded-md border border-sand-300 bg-sand-100 px-4 py-3 text-sm font-semibold text-sand-700">
            <i class="ti ti-lock" aria-hidden="true"></i>
            {{ $summary['status'] }} — submitted {{ $municipalReport->mrp_updated_at->format('M j, Y g:i A') }} by {{ $municipalReport->submitter->usr_name ?? '—' }}. This report can no longer be changed here.
        </div>
    @elseif ($summary['pendingCount'] > 0)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-alert-triangle" aria-hidden="true"></i> {{ $summary['pendingCount'] }} establishment report(s) still need review or correction before this report can be submitted.</p>
            <a href="{{ route('lgu.monthlyReports.index', ['period' => $period]) }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Go to Monthly Reports</a>
        </div>
    @elseif ($summary['canSubmit'])
        <div class="mt-6 flex items-center gap-2 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm font-semibold text-success">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            Ready for submission. Check the Report Preview, then Submit to PTO.
        </div>
    @else
        <div class="mt-6 flex items-center gap-2 rounded-md border border-sand-300 bg-sand-100 px-4 py-3 text-sm font-semibold text-sand-700">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            No verified establishment reports for this month yet.
        </div>
    @endif

    @if ($balanceErrors !== [] && ! $summary['isSentToPto'])
        <div class="mt-4 rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="font-semibold">PTO cannot verify a report whose columns don't add up. Correct these establishment reports first:</p>
            <ul class="mt-1 list-disc pl-5 text-xs">
                @foreach ($balanceErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-dashboard.kpi-card label="Completeness" :value="$summary['verifiedCount'].' of '.$summary['establishmentCount'].' establishments verified'" tone="neutral" />
        <x-dashboard.kpi-card label="Verified Reports" :value="$summary['verifiedCount']" tone="success" />
        <x-dashboard.kpi-card label="Pending Review" :value="$summary['pendingCount']" tone="warning" />
        <x-dashboard.kpi-card label="Not Submitted" :value="$summary['notSubmittedCount']" tone="danger" />
        <x-dashboard.kpi-card label="Total Verified Arrivals" :value="number_format($summary['verifiedTotal'])" tone="neutral" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Visitor Classifications</h2>
            <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([
                    'Male' => $visitorBreakdown['male'],
                    'Female' => $visitorBreakdown['female'],
                    'Adults' => $visitorBreakdown['adults'],
                    'Children' => $visitorBreakdown['children'],
                    'Seniors' => $visitorBreakdown['seniors'],
                    'Local' => $visitorBreakdown['local'],
                    'Foreign' => $visitorBreakdown['foreign'],
                    'Total' => $visitorBreakdown['total'],
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">{{ $label }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-sand-900">{{ number_format($value) }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-xs text-sand-500">From verified establishment reports only.</p>
        </div>

        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Report Information</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt>
                    <dd class="mt-1"><x-dashboard.status-badge :tone="$summary['statusTone']">{{ $summary['status'] }}</x-dashboard.status-badge></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Reference No.</dt>
                    <dd class="mt-1 text-sand-800">{{ $municipalReport ? sprintf('MRP-%06d', $municipalReport->mrp_id) : 'Assigned when submitted' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted to PTO</dt>
                    <dd class="mt-1 text-sand-800">{{ $municipalReport ? ($municipalReport->submitter->usr_name ?? '—').' · '.$municipalReport->mrp_updated_at->format('M j, Y') : 'Not yet' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">PTO Review</dt>
                    <dd class="mt-1 text-sand-800">{{ $municipalReport?->mrp_reviewed_at ? ($municipalReport->reviewer->usr_name ?? 'PTO').' · '.$municipalReport->mrp_reviewed_at->format('M j, Y') : 'Not yet' }}</dd>
                </div>
                @if ($municipalReport?->mrp_verification_code)
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Verification Code</dt>
                        <dd class="mt-1 font-mono text-sand-800">{{ $municipalReport->mrp_verification_code }}</dd>
                    </div>
                @endif
                @if ($municipalReport && $municipalReport->mrp_revision_number > 1)
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Revision</dt>
                        <dd class="mt-1 text-sand-800">No. {{ $municipalReport->mrp_revision_number }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <div class="border-b border-sand-200 px-5 py-4">
            <h2 class="font-display text-base font-bold text-sand-900">Establishment Breakdown</h2>
            <p class="text-xs text-sand-500">Verified reports are added to the municipal total automatically. Press an establishment name to open its report.</p>
        </div>
        <table class="w-full min-w-[1000px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3">Establishment</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Male</th>
                    <th class="px-4 py-3 text-right">Female</th>
                    <th class="px-4 py-3 text-right">Local</th>
                    <th class="px-4 py-3 text-right">Foreign</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Verified By</th>
                    <th class="px-4 py-3">In Municipal Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach ($rows as $row)
                    @php
                        $report = $row['report'];
                        $isCounted = $report && $report->mar_status === \App\Enums\MonthlyReportStatus::Verified;
                    @endphp
                    <tr @class(['text-sand-500' => ! $isCounted])>
                        <td class="px-4 py-3">
                            @if ($report)
                                <a href="{{ route('lgu.monthlyReports.show', $report) }}" class="font-medium text-sand-900 hover:text-primary-700">{{ $row['listing']->name }}</a>
                            @else
                                <span class="font-medium text-sand-900">{{ $row['listing']->name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $report?->mar_submission_source->label() ?? '—' }}</td>
                        <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                        <td class="px-4 py-3 text-right">{{ $report ? number_format($report->mar_party_male) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $report ? number_format($report->mar_party_female) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $report ? number_format($report->mar_party_local) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $report ? number_format($report->mar_party_foreign) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $report ? number_format($report->mar_total_visitors) : '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $isCounted ? ($report->verifier->name ?? '—').' · '.$report->mar_verified_at?->format('M j') : '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $isCounted ? 'Yes' : ($report ? 'Not yet — awaiting verification' : 'No — Not Submitted') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-sand-300 bg-sand-50 font-semibold text-sand-900">
                    <td class="px-4 py-3" colspan="3">Municipal Total (verified reports only)</td>
                    <td class="px-4 py-3 text-right">{{ number_format($summary['verifiedReports']->sum('party_male')) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($summary['verifiedReports']->sum('party_female')) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($summary['verifiedReports']->sum('party_local')) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($summary['verifiedReports']->sum('party_foreign')) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($summary['verifiedTotal']) }}</td>
                    <td class="px-4 py-3" colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($history->isNotEmpty())
        <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Submission History</h2>
            <table class="w-full min-w-[640px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">By</th>
                        <th class="px-4 py-3">Reason / Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($history as $entry)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sand-700">{{ $entry->opl_created_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="\App\Models\OperationLog::badgeTone($entry->opl_action)">{{ ucfirst(str_replace('_', ' ', $entry->opl_action)) }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-sand-700">{{ $entry->user->usr_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-600">{{ $entry->opl_reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.dashboard>
