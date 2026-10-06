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
        default => 'danger',
    };
    $notSubmittedRows = $rows->whereNull('report');
    $isForReview = fn ($report) => $report && $report->mar_status === \App\Enums\MonthlyReportStatus::ForReview;
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Tourism Reports"
        description="Monitor, review, and consolidate monthly reports from tourism establishments in {{ $municipality }}."
    >
        <x-slot:actions>
            <button type="button" data-modal-open="encode-paper-modal" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-pencil" aria-hidden="true"></i>
                Encode Paper Report
            </button>

            @if (! $alreadyConsolidated && $verifiedCount > 0)
                <form method="POST" action="{{ route('lgu.monthlyReports.consolidate') }}">
                    @csrf
                    <input type="hidden" name="period_month" value="{{ $month->format('Y-m') }}">
                    <button
                        type="button"
                        data-confirm-trigger
                        data-confirm-title="Consolidate and submit to PTO?"
                        data-confirm-message="This sums {{ $verifiedCount }} verified report(s) for {{ $month->format('F Y') }} into {{ $municipality }}'s municipal report and submits it to PTO."
                        data-confirm-label="Consolidate & Submit"
                        class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                    >
                        <i class="ti ti-report" aria-hidden="true"></i>
                        Monthly Consolidation
                    </button>
                </form>
            @endif
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

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-dashboard.kpi-card label="Total Establishments" :value="$rows->count()" tone="neutral" />
        <x-dashboard.kpi-card label="Submitted" :value="$submittedCount" tone="neutral" />
        <x-dashboard.kpi-card label="For Review" :value="$forReviewCount" delta="{{ $forReviewCount ? 'Needs your action' : null }}" tone="warning" />
        <x-dashboard.kpi-card label="Not Submitted" :value="$missingCount" delta="{{ $missingCount ? 'Follow up required' : null }}" tone="danger" />
        <x-dashboard.kpi-card label="Verified" :value="$verifiedCount" tone="success" />
    </div>

    <div data-filterable-table data-page-size="10" class="mt-6">
        <div class="rounded-t-md border border-b-0 border-sand-200 bg-sand-0 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-bold text-sand-900">Establishment Reporting Status · {{ $month->format('F Y') }}</h2>
                    <p class="text-xs text-sand-500">Reports needing attention are listed first.</p>
                </div>
                <div class="flex items-center gap-2">
                    <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                        <option value="">All</option>
                        <option value="Not Submitted">Not Submitted</option>
                        <option value="For Review">For Review</option>
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
                        <th class="px-4 py-3">Submission Source</th>
                        <th class="px-4 py-3">Submitted / Encoded</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Arrival Total</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @forelse ($rows as $row)
                        <tr data-row data-status="{{ $row['status'] }}" data-search-text="{{ strtolower($row['listing']->lst_name) }}" @class(['hover:bg-sand-50', 'bg-danger-bg/40' => ! $row['report']])>
                            <td class="px-4 py-3">
                                <p class="font-medium text-sand-900">{{ $row['listing']->lst_name }}</p>
                                <p class="text-xs text-sand-500">{{ \Illuminate\Support\Str::headline($row['listing']->lst_category) }}</p>
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $month->format('F Y') }}</td>
                            <td class="px-4 py-3 text-sand-700">
                                @if ($row['report'])
                                    <span class="inline-flex items-center gap-1.5"><i class="ti {{ $row['report']->mar_submission_source->icon() }}" aria-hidden="true"></i> {{ $row['report']->mar_submission_source->label() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $row['report']?->mar_submitted_at->format('M j') ?? '—' }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $row['report'] ? number_format($row['report']->mar_total_visitors) : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (! $row['report'])
                                    <a href="{{ route('lgu.monthlyReports.manualEntry', ['listing' => $row['listing'], 'period' => $month->format('Y-m')]) }}" class="text-sm font-semibold text-primary-700 hover:text-primary-900">
                                        Encode paper
                                    </a>
                                @elseif ($isForReview($row['report']))
                                    <a href="{{ route('lgu.monthlyReports.show', $row['report']) }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">
                                        Review
                                    </a>
                                @else
                                    <a href="{{ route('lgu.monthlyReports.show', $row['report']) }}" class="text-sm font-semibold text-sand-700 hover:text-primary-700">
                                        View
                                    </a>
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

    <x-dashboard.modal id="encode-paper-modal" title="Encode a Paper Report">
        @if ($notSubmittedRows->isEmpty())
            <p class="text-sm text-sand-600">Every establishment already has a report for {{ $month->format('F Y') }}.</p>
        @else
            <p class="text-sm text-sand-600">Pick the establishment whose physical report you're encoding for {{ $month->format('F Y') }}.</p>
            <ul class="mt-4 flex flex-col gap-1">
                @foreach ($notSubmittedRows as $row)
                    <li>
                        <a href="{{ route('lgu.monthlyReports.manualEntry', ['listing' => $row['listing'], 'period' => $month->format('Y-m')]) }}" class="flex items-center justify-between rounded-sm px-2.5 py-2 text-sm text-sand-800 hover:bg-sand-100">
                            {{ $row['listing']->lst_name }}
                            <i class="ti ti-chevron-right text-sand-400" aria-hidden="true"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-dashboard.modal>
</x-layouts.dashboard>
