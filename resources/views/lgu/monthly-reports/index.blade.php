{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Tourism Reports page — establishment-by-month status table.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $statusTone = fn (?string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        'Submitted' => 'info',
        'Draft' => 'neutral',
        default => 'danger',
    };
    $statusIs = fn ($report, \App\Enums\MonthlyReportStatus $status) => $report && $report->mar_status === $status;
    $period = $month->format('Y-m');
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Monthly Reports"
        description="Receive, review, and verify the monthly reports of establishments in {{ $municipality }}. Verified reports are added to your Municipal Report automatically."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.monthlyReports.manualEntry.index', ['period' => $period]) }}" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-pencil" aria-hidden="true"></i>
                Manual Entry
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="GET" action="{{ route('lgu.monthlyReports.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <div>
            <label for="period" class="mb-1 block text-xs font-semibold text-sand-700">Reporting Month</label>
            <select id="period" name="period" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($monthOptions as $option)
                    <option value="{{ $option->format('Y-m') }}" @selected($option->isSameMonth($month))>{{ $option->format('F Y') }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="mt-6">
        <x-dashboard.workflow-steps :steps="$steps" />
    </div>

    {{-- Next step: tell the LGU where to go once every received report is decided. --}}
    @if ($summary['canSubmit'])
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm text-success">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-circle-check" aria-hidden="true"></i> All received reports for {{ $month->format('F Y') }} are decided. The municipal report is ready to preview and submit.</p>
            <a href="{{ route('lgu.monthlyReports.municipal.show', $period) }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Go to Municipal Report</a>
        </div>
    @elseif ($summary['isSentToPto'])
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-sand-300 bg-sand-100 px-4 py-3 text-sm text-sand-700">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-send" aria-hidden="true"></i> The {{ $month->format('F Y') }} municipal report status: {{ $summary['status'] }}.</p>
            <a href="{{ route('lgu.monthlyReports.municipal.show', $period) }}" class="text-xs font-semibold text-primary-700 hover:text-primary-900">View Municipal Report</a>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-display text-base font-bold text-sand-900">{{ $month->format('F Y') }}</h2>
        <p class="text-sm font-semibold text-sand-700">{{ $verifiedCount }} of {{ $rows->count() }} establishments verified</p>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-dashboard.kpi-card label="Total Establishments" :value="$rows->count()" tone="neutral" />
        <x-dashboard.kpi-card label="Submitted" :value="$submittedCount" tone="neutral" />
        <x-dashboard.kpi-card label="Verified" :value="$verifiedCount" tone="success" />
        <x-dashboard.kpi-card label="For Review" :value="$forReviewCount" delta="{{ $forReviewCount ? 'Needs your action' : null }}" tone="warning" />
        <x-dashboard.kpi-card label="Not Submitted" :value="$missingCount" delta="{{ $missingCount ? 'Follow up required' : null }}" tone="danger" />
    </div>

    <div data-filterable-table data-page-size="10" class="mt-6">
        <div class="rounded-t-md border border-b-0 border-sand-200 bg-sand-0 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-bold text-sand-900">Establishment Reports · {{ $month->format('F Y') }}</h2>
                    <p class="text-xs text-sand-500">Reports needing attention are listed first. A missing report is Not Submitted, never zero arrivals.</p>
                </div>
                <div class="flex items-center gap-2">
                    <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                        <option value="">All</option>
                        <option value="Not Submitted">Not Submitted</option>
                        <option value="Submitted">Submitted</option>
                        <option value="For Review">For Review</option>
                        <option value="For Correction">For Correction</option>
                        <option value="Verified">Verified</option>
                    </select>
                    <div class="flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                        <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                        <input data-filter-input type="search" placeholder="Search establishment..." class="w-48 border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-b-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[920px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Establishment</th>
                        <th class="px-4 py-3">Reporting Month</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Submitted / Encoded</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Arrival Total</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @forelse ($rows as $row)
                        @php($report = $row['report'])
                        <tr data-row data-status="{{ $row['status'] }}" data-search-text="{{ strtolower($row['listing']->lst_name) }}" @class(['hover:bg-sand-50', 'bg-danger-bg/40' => ! $report])>
                            <td class="px-4 py-3">
                                <p class="font-medium text-sand-900">{{ $row['listing']->lst_name }}</p>
                                <p class="text-xs text-sand-500">{{ $row['listing']->categoryName() }} · {{ $row['listing']->reportingMethod()->label() }}</p>
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $month->format('F Y') }}</td>
                            <td class="px-4 py-3 text-sand-700">
                                @if ($report)
                                    <span class="inline-flex items-center gap-1.5"><i class="ti {{ $report->mar_submission_source->icon() }}" aria-hidden="true"></i> {{ $report->mar_submission_source->label() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $report?->mar_submitted_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $report ? number_format($report->mar_total_visitors) : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (! $report && $row['draftInProgress'])
                                    <span class="text-xs text-sand-500">Draft in progress</span>
                                @elseif (! $report && $row['listing']->reportingMethod()->isOnline())
                                    {{-- Online iTOUR establishments submit their own report; no second (paper) source. --}}
                                    <span class="text-xs text-sand-500">Awaiting establishment</span>
                                @elseif (! $report)
                                    <a href="{{ route('lgu.monthlyReports.manualEntry', ['listing' => $row['listing'], 'period' => $period]) }}" class="text-sm font-semibold text-primary-700 hover:text-primary-900">
                                        Manual Entry
                                    </a>
                                @elseif ($statusIs($report, \App\Enums\MonthlyReportStatus::Submitted))
                                    <form method="POST" action="{{ route('lgu.monthlyReports.review', $report) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Review</button>
                                    </form>
                                @elseif ($statusIs($report, \App\Enums\MonthlyReportStatus::ForReview))
                                    <a href="{{ route('lgu.monthlyReports.show', $report) }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">
                                        Verify / Return
                                    </a>
                                @elseif ($statusIs($report, \App\Enums\MonthlyReportStatus::Draft))
                                    <a href="{{ route('lgu.monthlyReports.show', $report) }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                        Continue Draft
                                    </a>
                                @else
                                    <button type="button" data-modal-open="lgu-report-modal-{{ $report->mar_id }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                        View
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sand-500">No establishments found for this municipality.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $rows->count() }}</span> of {{ $rows->count() }} establishments</p>

        <x-dashboard.empty-state
            data-empty-state
            class="hidden mt-3"
            icon="ti-map-search"
            title="No establishments match"
            description="Try a different search term or status filter."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>

    {{-- Quick-view modals for View (Verified / For Correction rows) — the page stays put. --}}
    @foreach ($rows as $row)
        @php($report = $row['report'])
        @if ($report && in_array($report->mar_status, [\App\Enums\MonthlyReportStatus::Verified, \App\Enums\MonthlyReportStatus::ForCorrection], true))
            @include('lgu.monthly-reports.partials.report-modal', ['report' => $report, 'listing' => $row['listing']])
        @endif
    @endforeach
</x-layouts.dashboard>
