{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: PTO Provincial Reports — the PTO's one reporting workspace. One
    Reporting Year selector drives three in-page tabs (no page change):
      Overview        — province-wide reporting situation for the year
      Monthly Records — one consolidated provincial record per month; View /
                        Review open that month's modal (every LGU, with
                        Verify / Return for LGU reports waiting on PTO)
      Statistics      — PTO-verified figures, trend, LGU coverage
    Official totals count PTO-verified LGU (municipal) reports only.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $stats = $statistics;
    $summary = $stats['summary'];
    $changeTone = fn (string $label) => match ($label) {
        'Increased' => 'success',
        'Decreased' => 'danger',
        default => 'neutral',
    };
    $percentOf = fn (int $value, int $total) => $total > 0 ? round($value / $total * 100, 1) : 0;
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')" :show-breadcrumb="false">
    <x-dashboard.page-header
        title="Provincial Reports"
        description="The province's consolidated tourism reporting records, built from LGU reports. Only reports verified by the PTO count in the official totals."
    />

    <form method="GET" action="{{ route('pto.monthlyReports.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
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

    <x-dashboard.tabs sync="tab" panel-host="#provincial-reports-panels" :tabs="[
        ['label' => 'Overview', 'panel' => 'overview', 'active' => $activeTab === 'overview', 'icon' => 'ti-layout-dashboard'],
        ['label' => 'Monthly Records', 'panel' => 'records', 'active' => $activeTab === 'records', 'icon' => 'ti-calendar-event'],
        ['label' => 'Statistics', 'panel' => 'statistics', 'active' => $activeTab === 'statistics', 'icon' => 'ti-chart-bar'],
    ]" />

    <div id="provincial-reports-panels">
        {{-- ===================== OVERVIEW ===================== --}}
        <div data-tab-panel="overview" @class(['hidden' => $activeTab !== 'overview'])>
            <h2 class="mt-6 font-display text-base font-bold text-sand-900">{{ $year }} Provincial Reporting Overview</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-3">
                <x-dashboard.kpi-card label="Total Tourist Arrivals" :value="number_format($overview['total'])" delta="PTO-verified LGU reports only" tone="neutral" />
                <x-dashboard.kpi-card label="LGUs Reported" :value="$overview['lgusReported'].' of '.$municipalityCount" tone="neutral" />
                <x-dashboard.kpi-card label="Verified LGU Reports" :value="$overview['verifiedCount']" tone="success" />
                <x-dashboard.kpi-card label="Reports for Correction" :value="$overview['forCorrectionCount']" tone="danger" />
                <x-dashboard.kpi-card label="Months Completed" :value="$overview['monthsCompleted'].' of '.$overview['monthsElapsed']" delta="All LGUs verified" tone="neutral" />
                <x-dashboard.kpi-card label="Reporting Coverage" :value="$overview['coveragePercent'].'%'" delta="Verified LGU reports out of all expected" tone="neutral" />
            </div>

            <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">Waiting for Your Review</h2>
                @if ($overview['pendingReports']->isEmpty())
                    <p class="mt-2 flex items-center gap-2 text-sm text-success"><i class="ti ti-circle-check" aria-hidden="true"></i> No LGU reports are waiting for PTO review.</p>
                @else
                    <ul class="mt-3 divide-y divide-sand-100">
                        @foreach ($overview['pendingReports'] as $pending)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <p><span class="font-semibold text-sand-900">{{ $pending['municipality']->mun_name }}</span> <span class="text-sand-600">· {{ $pending['month']->format('F Y') }} · {{ number_format($pending['report']->mrp_total_arrivals) }} arrivals</span></p>
                                <button type="button" data-modal-open="province-month-{{ $pending['month']->month }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Review</button>
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
                    <p class="text-xs text-sand-500">One consolidated provincial record per month. View or Review opens the month's details without leaving this page.</p>
                </div>
                <table class="w-full min-w-[820px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3 text-right">Total Tourist Arrivals</th>
                            <th class="px-4 py-3 text-right">LGUs Reported</th>
                            <th class="px-4 py-3 text-right">Verified Reports</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @forelse ($months as $monthRow)
                            <tr class="hover:bg-sand-50">
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $monthRow['month']->format('F Y') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-900">{{ $monthRow['approvedCount'] > 0 ? number_format($monthRow['record']['total']) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-sand-700">{{ $monthRow['reportedCount'] }}/{{ $municipalityCount }}</td>
                                <td class="px-4 py-3 text-right text-sand-700">{{ $monthRow['approvedCount'] }}</td>
                                <td class="px-4 py-3"><x-dashboard.status-badge :tone="$monthRow['statusTone']">{{ $monthRow['status'] }}</x-dashboard.status-badge></td>
                                <td class="px-4 py-3 text-right">
                                    @if ($monthRow['action'] === 'review')
                                        <button type="button" data-modal-open="province-month-{{ $monthRow['month']->month }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Review</button>
                                    @elseif ($monthRow['action'] === 'view')
                                        <button type="button" data-modal-open="province-month-{{ $monthRow['month']->month }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">View</button>
                                    @else
                                        <span class="text-xs text-sand-400">—</span>
                                    @endif
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
                <x-dashboard.empty-state class="mt-6" icon="ti-chart-bar" title="No PTO-verified LGU reports for {{ $year }} yet" description="Statistics appear once LGU reports are verified by the PTO." />
            @else
                <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-base font-bold text-sand-900">Monthly Tourist Arrivals — {{ $year }}</h2>
                            <p class="text-xs text-sand-500">PTO-verified LGU reports only. "No report" means no verified LGU report for that month yet.</p>
                        </div>
                        @if ($stats['hasPrevious'])
                            <a href="{{ route('pto.monthlyReports.index', ['year' => $year, 'tab' => 'statistics', 'compare' => $stats['compare'] ? 0 : 1]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                <i class="ti ti-arrows-left-right" aria-hidden="true"></i>
                                {{ $stats['compare'] ? 'Hide '.($year - 1) : $year.' vs '.($year - 1) }}
                            </a>
                        @else
                            <span class="text-xs text-sand-500">No comparison available — no verified {{ $year - 1 }} reports.</span>
                        @endif
                    </div>

                    <x-dashboard.month-bar-chart
                        class="mt-4"
                        :records="$stats['records']"
                        :label="(string) $year"
                        :compare-records="$stats['compare'] ? $stats['previousRecords'] : null"
                        :compare-label="(string) ($year - 1)"
                    />

                    <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 sm:grid-cols-2 lg:grid-cols-4">
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
                            <dt class="text-xs font-semibold text-sand-500 uppercase">{{ $year }} vs {{ $year - 1 }}</dt>
                            <dd class="mt-1 text-sm text-sand-800">
                                <x-dashboard.status-badge :tone="$changeTone($stats['yearComparison']['label'])">{{ $stats['yearComparison']['label'] }}</x-dashboard.status-badge>
                                @if ($stats['yearComparison']['percent'] !== null)
                                    <span class="block text-xs text-sand-500">{{ $stats['yearComparison']['percent'] > 0 ? '+' : '' }}{{ $stats['yearComparison']['percent'] }}% ({{ $stats['yearComparison']['periodLabel'] }})</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
                        <h2 class="font-display text-base font-bold text-sand-900">Visitor Classifications — {{ $year }}</h2>
                        @php($visitors = $stats['visitorBreakdown'])
                        @if ($visitors['total'] === 0)
                            <p class="mt-3 text-sm text-sand-500">Classification details are not available for these reports (they have no establishment-level breakdown).</p>
                        @else
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
                        @endif
                    </div>

                    <div class="overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                        <div class="border-b border-sand-200 px-5 py-4">
                            <h2 class="font-display text-base font-bold text-sand-900">LGU Reporting Coverage — {{ $year }}</h2>
                            <p class="text-xs text-sand-500">Months each LGU has reported and how many the PTO verified.</p>
                        </div>
                        <table class="w-full min-w-[480px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                    <th class="px-4 py-3">LGU</th>
                                    <th class="px-4 py-3 text-right">Reported</th>
                                    <th class="px-4 py-3 text-right">Verified</th>
                                    <th class="px-4 py-3 text-right">Verified Arrivals</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sand-100">
                                @foreach ($stats['lguCoverage'] as $coverage)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium text-sand-900">{{ $coverage['municipality']->mun_name }}</td>
                                        <td class="px-4 py-2.5 text-right text-sand-700">{{ $coverage['submittedMonths'] }} of {{ $coverage['expectedMonths'] }}</td>
                                        <td class="px-4 py-2.5 text-right text-sand-700">{{ $coverage['verifiedMonths'] }}</td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-sand-900">{{ number_format($coverage['verifiedArrivals']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- One View / Review modal per month — stays on this page and tab. --}}
    @foreach ($months->whereNotNull('action') as $monthRow)
        @include('pto.monthly-reports.partials.month-modal', ['monthRow' => $monthRow, 'municipalityCount' => $municipalityCount])
    @endforeach
</x-layouts.dashboard>
