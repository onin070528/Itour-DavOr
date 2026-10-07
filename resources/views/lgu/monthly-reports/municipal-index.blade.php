{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Municipal Reports — the municipality's official consolidated
    reporting record and tourism monitoring area for one year. Answers "What
    is the overall tourism performance of this municipality, and what are
    its official consolidated records?" in three sections:
      Overview        — yearly totals, monthly trend chart, year comparison
      Monthly Records — one consolidated report per month (View / Report Preview
                        / Submit to PTO) and the quarterly summary
      Statistics      — by visitor classification, category, establishment
    Every figure is derived from Verified establishment reports only; a
    month or establishment with no verified report is never counted as zero.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $changeTone = fn (string $label) => match ($label) {
        'Increased' => 'success',
        'Decreased' => 'danger',
        default => 'neutral',
    };
    $sectionUrl = fn (string $section) => route('lgu.monthlyReports.municipal', ['year' => $year, 'section' => $section]);
    $percentOf = fn (int $value, int $total) => $total > 0 ? round($value / $total * 100, 1) : 0;
    $readyMonths = $months->where('canSubmit', true);
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Municipal Reports"
        description="{{ $municipality }}'s official tourism records, built automatically from the establishment reports you verified."
    />

    <form method="GET" action="{{ route('lgu.monthlyReports.municipal') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <input type="hidden" name="section" value="{{ $section }}">
        <div>
            <label for="year" class="mb-1 block text-sm font-semibold text-sand-700">Year</label>
            <select id="year" name="year" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($yearOptions as $yearOption)
                    <option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>
                @endforeach
            </select>
        </div>
        <p class="pb-2.5 text-xs text-sand-500">Only <span class="font-semibold">verified</span> establishment reports are counted. Establishments that did not report are never counted as zero.</p>
    </form>

    {{-- Next step first: a month ready to send to PTO. --}}
    @foreach ($readyMonths as $ready)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm text-success">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-send" aria-hidden="true"></i> The {{ $ready['month']->format('F Y') }} municipal report is ready to send to PTO.</p>
            <a href="{{ route('lgu.monthlyReports.municipal.show', $ready['month']->format('Y-m')) }}" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Review &amp; Submit</a>
        </div>
    @endforeach

    <x-dashboard.tabs :tabs="[
        ['label' => 'Overview', 'href' => $sectionUrl('overview'), 'active' => $section === 'overview', 'icon' => 'ti-chart-bar'],
        ['label' => 'Monthly Records', 'href' => $sectionUrl('records'), 'active' => $section === 'records', 'icon' => 'ti-calendar-event'],
        ['label' => 'Statistics', 'href' => $sectionUrl('statistics'), 'active' => $section === 'statistics', 'icon' => 'ti-chart-pie'],
    ]" />

    @if ($section === 'overview')
        <h2 class="mt-6 font-display text-base font-bold text-sand-900">Municipal Tourism Overview — {{ $year }}</h2>
        <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-dashboard.kpi-card label="Total Tourist Arrivals" :value="number_format($summary['total'])" tone="neutral" />
            <x-dashboard.kpi-card label="Verified Establishment Reports" :value="$verifiedReportCount" tone="success" />
            <x-dashboard.kpi-card label="Reporting Establishments" :value="$reportingEstablishmentCount.' of '.$establishmentCount" tone="neutral" />
            <x-dashboard.kpi-card label="Highest Month" :value="$summary['highest'] ? $summary['highest']['shortLabel'].' · '.number_format($summary['highest']['total']) : '—'" tone="success" />
            <x-dashboard.kpi-card label="Lowest Month" :value="$summary['lowest'] ? $summary['lowest']['shortLabel'].' · '.number_format($summary['lowest']['total']) : '—'" tone="neutral" />
        </div>

        <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-bold text-sand-900">Monthly Tourist Arrivals — {{ $year }}</h2>
                    <p class="text-xs text-sand-500">"No report" means no verified establishment report for that month yet.</p>
                </div>
                @if ($hasPrevious)
                    <a href="{{ route('lgu.monthlyReports.municipal', ['year' => $year, 'section' => 'overview', 'compare' => $compare ? 0 : 1]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-xs font-semibold text-sand-800 hover:border-primary-300">
                        <i class="ti ti-arrows-left-right" aria-hidden="true"></i>
                        {{ $compare ? 'Hide '.($year - 1) : $year.' vs '.($year - 1) }}
                    </a>
                @else
                    <span class="text-xs text-sand-500">No {{ $year - 1 }} records — year comparison not available.</span>
                @endif
            </div>

            @if ($summary['monthsWithData'] === 0)
                <x-dashboard.empty-state class="mt-4" icon="ti-chart-bar" title="No verified reports for {{ $year }} yet" description="Verify establishment reports under Monthly Reports and they will appear here." />
            @else
                <x-dashboard.month-bar-chart
                    class="mt-4"
                    :records="$records"
                    :label="(string) $year"
                    :compare-records="$compare ? $previousRecords : null"
                    :compare-label="(string) ($year - 1)"
                />

                <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Yearly Total</dt>
                        <dd class="mt-1 text-sm text-sand-800"><span class="font-semibold">{{ number_format($summary['total']) }}</span> <span class="text-xs text-sand-500">from {{ $summary['monthsWithData'] }} reported {{ \Illuminate\Support\Str::plural('month', $summary['monthsWithData']) }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Average per Reported Month</dt>
                        <dd class="mt-1 text-sm text-sand-800">{{ number_format($summary['average']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">{{ $year }} vs {{ $year - 1 }}</dt>
                        <dd class="mt-1 text-sm text-sand-800">
                            <x-dashboard.status-badge :tone="$changeTone($yearComparison['label'])">{{ $yearComparison['label'] }}</x-dashboard.status-badge>
                            @if ($yearComparison['percent'] !== null)
                                <span class="text-xs text-sand-500">{{ $yearComparison['percent'] > 0 ? '+' : '' }}{{ $yearComparison['percent'] }}% · {{ number_format($yearComparison['current']) }} vs {{ number_format($yearComparison['previous']) }} ({{ $yearComparison['periodLabel'] }})</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            @endif
        </div>

        @if ($establishmentBreakdown->isNotEmpty())
            <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Top Performing Establishments — {{ $year }}</h2>
                <table class="w-full min-w-[520px] border-collapse text-sm">
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($establishmentBreakdown->take(5) as $index => $establishment)
                            <tr>
                                <td class="w-10 px-4 py-3 font-bold text-primary-700">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $establishment['name'] }} <span class="text-xs text-sand-500">· {{ $establishment['category'] }}</span></td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-900">{{ number_format($establishment['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($section === 'records')
        <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <div class="border-b border-sand-200 px-5 py-4">
                <h2 class="font-display text-base font-bold text-sand-900">Monthly Municipal Records — {{ $year }}</h2>
                <p class="text-xs text-sand-500">One official consolidated report per month. Press View for a quick look, or Report Preview to see the printable copy.</p>
            </div>
            <table class="w-full min-w-[1040px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3 text-right">Total Arrivals</th>
                        <th class="px-4 py-3">vs Previous Month</th>
                        <th class="px-4 py-3 text-right">Establishments Verified</th>
                        <th class="px-4 py-3 text-right">Waiting for Review</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @forelse ($months as $entry)
                        @php($period = $entry['month']->format('Y-m'))
                        <tr class="hover:bg-sand-50">
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $entry['month']->format('F Y') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-900">{{ $entry['verifiedCount'] > 0 ? number_format($entry['verifiedTotal']) : '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($entry['record']['hasData'])
                                    <x-dashboard.status-badge :tone="$changeTone($entry['record']['change'])">{{ $entry['record']['change'] }}</x-dashboard.status-badge>
                                @else
                                    <span class="text-xs text-sand-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sand-700">{{ $entry['verifiedCount'] }} of {{ $entry['establishmentCount'] }}</td>
                            <td class="px-4 py-3 text-right text-sand-700">{{ $entry['pendingCount'] }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$entry['statusTone']">{{ $entry['status'] }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($entry['canSubmit'])
                                        <a href="{{ route('lgu.monthlyReports.municipal.show', $period) }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Review &amp; Submit</a>
                                    @else
                                        <button type="button" data-modal-open="municipal-modal-{{ $period }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">View</button>
                                    @endif
                                    @if ($entry['verifiedCount'] > 0 || $entry['municipalReport'])
                                        <a href="{{ route('lgu.monthlyReports.municipal.preview', $period) }}" class="text-xs font-semibold text-sand-700 hover:text-primary-700">Report Preview</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sand-500">No months to show for {{ $year }} yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <div class="border-b border-sand-200 px-5 py-4">
                <h2 class="font-display text-base font-bold text-sand-900">Quarterly Summary — {{ $year }}</h2>
                <p class="text-xs text-sand-500">Added up automatically from the verified monthly records above.</p>
            </div>
            <table class="w-full min-w-[520px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Quarter</th>
                        <th class="px-4 py-3 text-right">Tourist Arrivals</th>
                        <th class="px-4 py-3">Months Reported</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($quarters as $quarter)
                        <tr>
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $quarter['quarter'] }} <span class="text-xs text-sand-500">({{ $quarter['months'] }})</span></td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-900">{{ $quarter['monthsWithData'] > 0 ? number_format($quarter['total']) : 'No report' }}</td>
                            <td class="px-4 py-3 text-sand-700">
                                {{ $quarter['monthsWithData'] }} of 3
                                @if ($quarter['monthsWithData'] > 0 && $quarter['monthsWithData'] < 3)
                                    <span class="text-xs text-sand-500">(incomplete)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="border-t-2 border-sand-300 bg-sand-50 font-semibold text-sand-900">
                        <td class="px-4 py-3">Year {{ $year }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($summary['total']) }}</td>
                        <td class="px-4 py-3">{{ $summary['monthsWithData'] }} of 12</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        @php($visitorTotal = $visitorBreakdown['total'])
        @if ($visitorTotal === 0)
            <x-dashboard.empty-state class="mt-6" icon="ti-chart-pie" title="No verified reports for {{ $year }} yet" description="Statistics appear once establishment reports are verified." />
        @else
            <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">By Visitor Classification — {{ $year }}</h2>
                <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach ([
                        'Gender' => ['Male' => $visitorBreakdown['male'], 'Female' => $visitorBreakdown['female']],
                        'Age Group' => ['Adults' => $visitorBreakdown['adults'], 'Children' => $visitorBreakdown['children'], 'Seniors' => $visitorBreakdown['seniors']],
                        'Origin' => ['Local' => $visitorBreakdown['local'], 'Foreign' => $visitorBreakdown['foreign']],
                    ] as $groupLabel => $groupValues)
                        <div>
                            <h3 class="text-xs font-semibold text-sand-500 uppercase">{{ $groupLabel }}</h3>
                            <ul class="mt-2 flex flex-col gap-2">
                                @foreach ($groupValues as $valueLabel => $value)
                                    <li>
                                        <div class="flex items-center justify-between text-sm text-sand-800">
                                            <span>{{ $valueLabel }}</span>
                                            <span class="font-semibold">{{ number_format($value) }} <span class="text-xs font-normal text-sand-500">({{ $percentOf($value, $visitorTotal) }}%)</span></span>
                                        </div>
                                        <div class="mt-1 h-2 rounded-full bg-sand-100"><div class="h-2 rounded-full bg-primary-700" style="width: {{ $percentOf($value, $visitorTotal) }}%"></div></div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <div class="border-b border-sand-200 px-5 py-4">
                    <h2 class="font-display text-base font-bold text-sand-900">By Category — {{ $year }}</h2>
                    <p class="text-xs text-sand-500">Arrivals by type of establishment. Destinations do not report arrivals yet, so they are not included.</p>
                </div>
                <table class="w-full min-w-[520px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Establishments</th>
                            <th class="px-4 py-3 text-right">Tourist Arrivals</th>
                            <th class="px-4 py-3 text-right">Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($categoryBreakdown as $category)
                            <tr>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $category['category'] }}</td>
                                <td class="px-4 py-3 text-right text-sand-700">{{ $category['establishments'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-900">{{ number_format($category['total']) }}</td>
                                <td class="px-4 py-3 text-right text-sand-700">{{ $percentOf($category['total'], $visitorTotal) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">By Establishment — {{ $year }}</h2>
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Establishment</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Verified Reports</th>
                            <th class="px-4 py-3 text-right">Tourist Arrivals</th>
                            <th class="px-4 py-3 text-right">Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($establishmentBreakdown as $establishment)
                            <tr>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $establishment['name'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $establishment['category'] }}</td>
                                <td class="px-4 py-3 text-right text-sand-700">{{ $establishment['reportCount'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-900">{{ number_format($establishment['total']) }}</td>
                                <td class="px-4 py-3 text-right text-sand-700">{{ $percentOf($establishment['total'], $visitorTotal) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- Quick-view modals for View — the page and section stay put. --}}
    @foreach ($months->where('canSubmit', false) as $entry)
        @include('lgu.monthly-reports.partials.municipal-modal', ['entry' => $entry])
    @endforeach
</x-layouts.dashboard>
