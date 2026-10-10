{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Establishment Monthly Reports — the one place where the
    establishment manages its monthly reports, sees its reporting history,
    and views its own tourism statistics. One Reporting Year selector drives
    three in-page tabs (switching tabs never leaves the page):
      Overview        — how reporting is going this year + what needs attention
      Monthly Records — every month up to now, with the one action each needs
      Statistics      — verified-only arrival figures, trend, classifications
    Only opening a single report goes to its own detail page.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@use('App\Enums\MonthlyReportStatus')
@php
    $summary = $overview['summary'];
    $changeTone = fn (string $label) => match ($label) {
        'Increased' => 'success',
        'Decreased' => 'danger',
        default => 'neutral',
    };
    $statusLabel = fn ($report) => $report?->mar_status->label() ?? 'Not Submitted';
    $statusTone = fn ($report) => $report?->mar_status->badgeTone() ?? 'danger';
    $percentOf = fn (int $value, int $total) => $total > 0 ? round($value / $total * 100, 1) : 0;

    // Months that need the establishment to do something, most urgent first:
    // returned for correction, then unfinished drafts, then past months
    // never prepared. The current month is still running, so it is not
    // flagged as overdue.
    $needsAttention = $months->filter(fn ($row) => $row['report']?->mar_status === MonthlyReportStatus::ForCorrection)
        ->concat($months->filter(fn ($row) => $row['report']?->mar_status === MonthlyReportStatus::Draft))
        ->concat($months->filter(fn ($row) => ! $row['report'] && ! $row['isCurrentMonth']));
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Monthly Reports"
        description="Prepare and send {{ $establishmentName }}'s monthly tourist-arrival reports, look back at past reports, and see your tourism statistics — all in one place."
    />

    <form method="GET" action="{{ route('establishment.arrivals.monthly') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <input type="hidden" name="tab" value="{{ $activeTab }}" data-active-tab-input="tab">
        <div>
            <label for="reporting-year" class="mb-1 block text-sm font-semibold text-sand-700">Reporting Year</label>
            <select id="reporting-year" name="year" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($yearOptions as $yearOption)
                    <option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>
                @endforeach
            </select>
        </div>
        <p class="pb-2.5 text-xs text-sand-500">Changing the year updates all three tabs below.</p>
    </form>

    <x-dashboard.tabs sync="tab" panel-host="#monthly-reports-panels" :tabs="[
        ['label' => 'Overview', 'panel' => 'overview', 'active' => $activeTab === 'overview', 'icon' => 'ti-layout-dashboard'],
        ['label' => 'Monthly Records', 'panel' => 'records', 'active' => $activeTab === 'records', 'icon' => 'ti-calendar-event'],
        ['label' => 'Statistics', 'panel' => 'statistics', 'active' => $activeTab === 'statistics', 'icon' => 'ti-chart-bar'],
    ]" />

    <div id="monthly-reports-panels">
        {{-- ===================== OVERVIEW ===================== --}}
        <div data-tab-panel="overview" @class(['hidden' => $activeTab !== 'overview'])>
            <h2 class="mt-6 font-display text-base font-bold text-sand-900">{{ $year }} Reporting Overview</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-5">
                <x-dashboard.kpi-card label="Total Tourist Arrivals" :value="number_format($summary['total'])" delta="Verified reports only" tone="neutral" />
                <x-dashboard.kpi-card label="Reports Submitted" :value="$overview['submittedCount']" tone="neutral" />
                <x-dashboard.kpi-card label="Verified Reports" :value="$overview['verifiedCount']" tone="success" />
                <x-dashboard.kpi-card label="For Correction" :value="$overview['forCorrectionCount']" tone="danger" />
                <x-dashboard.kpi-card label="Months Completed" :value="$overview['verifiedCount'].' of '.$months->count()" tone="neutral" />
            </div>

            <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">Monthly Reporting Progress — {{ $year }}</h2>
                <p class="text-xs text-sand-500">Where each month's report stands.</p>
                @if ($months->isEmpty())
                    <p class="mt-3 text-sm text-sand-500">No reporting months in {{ $year }} yet.</p>
                @else
                    <ul class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        @foreach ($months->sortBy(fn ($row) => $row['month']) as $row)
                            <li class="rounded-sm border border-sand-200 px-3 py-2">
                                <p class="text-xs font-semibold text-sand-700">{{ $row['month']->format('F') }}</p>
                                <p class="mt-1"><x-dashboard.status-badge :tone="$statusTone($row['report'])">{{ $statusLabel($row['report']) }}</x-dashboard.status-badge></p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">Needs Your Attention</h2>
                @if ($needsAttention->isEmpty())
                    <p class="mt-2 flex items-center gap-2 text-sm text-success"><i class="ti ti-circle-check" aria-hidden="true"></i> Nothing to do right now. Your reports for {{ $year }} are up to date.</p>
                @else
                    <ul class="mt-3 divide-y divide-sand-100">
                        @foreach ($needsAttention as $row)
                            @php($report = $row['report'])
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="text-sm">
                                    <p class="font-semibold text-sand-900">{{ $row['month']->format('F Y') }}</p>
                                    @if ($report?->mar_status === MonthlyReportStatus::ForCorrection)
                                        <p class="text-danger">Returned by the LGU: {{ $report->mar_remarks ?: 'please check the report.' }}</p>
                                    @elseif ($report?->mar_status === MonthlyReportStatus::Draft)
                                        <p class="text-sand-600">Saved as a draft but not sent to the LGU yet.</p>
                                    @else
                                        <p class="text-sand-600">No report has been prepared for this month yet.</p>
                                    @endif
                                </div>
                                @include('establishment.arrivals.partials.month-action', ['row' => $row])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- ===================== MONTHLY RECORDS ===================== --}}
        <div data-tab-panel="records" @class(['hidden' => $activeTab !== 'records'])>
            <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <div class="border-b border-sand-200 px-5 py-4">
                    <h2 class="font-display text-base font-bold text-sand-900">Monthly Records — {{ $year }}</h2>
                    <p class="text-xs text-sand-500">Each month's report and what to do next. The system adds up your recorded guests for you — there is nothing to count by hand.</p>
                </div>
                <table class="w-full min-w-[820px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3 text-right">Tourist Arrivals</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Submission Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @forelse ($months as $row)
                            @php($report = $row['report'])
                            <tr @class([
                                'hover:bg-sand-50',
                                'bg-danger-bg/40' => $report?->mar_status === MonthlyReportStatus::ForCorrection || (! $report && ! $row['isCurrentMonth']),
                            ])>
                                <td class="px-4 py-3 font-medium text-sand-900">
                                    {{ $row['month']->format('F Y') }}
                                    @if ($row['isCurrentMonth'])
                                        <span class="ml-1 text-xs font-normal text-sand-500">(this month)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $report ? number_format($report->mar_total_visitors) : '—' }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $report?->mar_submission_source->label() ?? '—' }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $report?->mar_submitted_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-dashboard.status-badge :tone="$statusTone($report)">{{ $statusLabel($report) }}</x-dashboard.status-badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end">
                                        @include('establishment.arrivals.partials.month-action', ['row' => $row])
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sand-500">No reporting months in {{ $year }} yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===================== STATISTICS ===================== --}}
        <div data-tab-panel="statistics" @class(['hidden' => $activeTab !== 'statistics'])>
            @if ($summary['monthsWithData'] === 0)
                <x-dashboard.empty-state class="mt-6" icon="ti-chart-bar" title="No verified reports for {{ $year }} yet" description="Statistics appear once the LGU verifies your monthly reports. Unverified reports are not counted." />
            @else
                <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-base font-bold text-sand-900">Monthly Tourist Arrivals — {{ $year }}</h2>
                            <p class="text-xs text-sand-500">Verified reports only. "No report" means the month has no verified report yet — it is not counted as zero.</p>
                        </div>
                        @if ($overview['hasPrevious'])
                            <a href="{{ route('establishment.arrivals.monthly', ['year' => $year, 'tab' => 'statistics', 'compare' => $overview['compare'] ? 0 : 1]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                <i class="ti ti-arrows-left-right" aria-hidden="true"></i>
                                {{ $overview['compare'] ? 'Hide '.($year - 1) : 'Compare with '.($year - 1) }}
                            </a>
                        @else
                            <span class="text-xs text-sand-500">Comparison not available — no verified {{ $year - 1 }} reports.</span>
                        @endif
                    </div>

                    <x-dashboard.month-bar-chart
                        class="mt-4"
                        :records="$overview['records']"
                        :label="(string) $year"
                        :compare-records="$overview['compare'] ? $overview['previousRecords'] : null"
                        :compare-label="(string) ($year - 1)"
                    />

                    <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <dt class="text-xs font-semibold text-sand-500 uppercase">Total for {{ $year }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-sand-900">{{ number_format($summary['total']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-sand-500 uppercase">Highest Month</dt>
                            <dd class="mt-1 text-sm text-sand-800">{{ $summary['highest']['label'] }} · <span class="font-semibold">{{ number_format($summary['highest']['total']) }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-sand-500 uppercase">Lowest Month</dt>
                            <dd class="mt-1 text-sm text-sand-800">{{ $summary['lowest']['label'] }} · <span class="font-semibold">{{ number_format($summary['lowest']['total']) }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-sand-500 uppercase">Average per Month</dt>
                            <dd class="mt-1 text-sm text-sand-800">
                                @if ($summary['monthsWithData'] >= 2)
                                    {{ number_format($summary['average']) }} <span class="text-xs text-sand-500">({{ $summary['monthsWithData'] }} months)</span>
                                @else
                                    <span class="text-xs text-sand-500">Needs at least 2 verified months</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-sand-500 uppercase">Compared with {{ $year - 1 }}</dt>
                            <dd class="mt-1 text-sm text-sand-800">
                                <x-dashboard.status-badge :tone="$changeTone($overview['yearComparison']['label'])">{{ $overview['yearComparison']['label'] }}</x-dashboard.status-badge>
                                @if ($overview['yearComparison']['percent'] !== null)
                                    <span class="block text-xs text-sand-500">{{ $overview['yearComparison']['percent'] > 0 ? '+' : '' }}{{ $overview['yearComparison']['percent'] }}% ({{ $overview['yearComparison']['periodLabel'] }})</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
                        <h2 class="font-display text-base font-bold text-sand-900">Visitor Classifications — {{ $year }}</h2>
                        @php($visitors = $overview['visitorBreakdown'])
                        <div class="mt-4 flex flex-col gap-5">
                            @foreach ([
                                'Gender' => ['Male' => $visitors['male'], 'Female' => $visitors['female']],
                                'Age Group' => ['Adults' => $visitors['adults'], 'Children' => $visitors['children'], 'Seniors' => $visitors['seniors']],
                                'Origin' => ['Local' => $visitors['local'], 'Foreign' => $visitors['foreign']],
                            ] as $groupLabel => $groupValues)
                                <div>
                                    <h3 class="text-xs font-semibold text-sand-500 uppercase">{{ $groupLabel }}</h3>
                                    <ul class="mt-2 flex flex-col gap-2">
                                        @foreach ($groupValues as $valueLabel => $value)
                                            <li>
                                                <div class="flex items-center justify-between text-sm text-sand-800">
                                                    <span>{{ $valueLabel }}</span>
                                                    <span class="font-semibold">{{ number_format($value) }} <span class="text-xs font-normal text-sand-500">({{ $percentOf($value, $visitors['total']) }}%)</span></span>
                                                </div>
                                                <div class="mt-1 h-2 rounded-full bg-sand-100"><div class="h-2 rounded-full bg-primary-700" style="width: {{ $percentOf($value, $visitors['total']) }}%"></div></div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                        <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Month by Month — {{ $year }}</h2>
                        <table class="w-full min-w-[420px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                    <th class="px-4 py-3">Month</th>
                                    <th class="px-4 py-3 text-right">Arrivals</th>
                                    <th class="px-4 py-3">vs Previous Month</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sand-100">
                                @foreach ($overview['records'] as $record)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium text-sand-900">{{ $record['label'] }}</td>
                                        <td class="px-4 py-2.5 text-right text-sand-800">{{ $record['hasData'] ? number_format($record['total']) : 'No report' }}</td>
                                        <td class="px-4 py-2.5">
                                            @if ($record['hasData'])
                                                <x-dashboard.status-badge :tone="$changeTone($record['change'])">{{ $record['change'] }}</x-dashboard.status-badge>
                                            @else
                                                <span class="text-xs text-sand-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Quick-view modals for View / View Remarks — the page and tab stay put. --}}
    @foreach ($months->whereNotNull('report') as $row)
        @include('establishment.arrivals.partials.report-modal', ['report' => $row['report']])
    @endforeach
</x-layouts.dashboard>
