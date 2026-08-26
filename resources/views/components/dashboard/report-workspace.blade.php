@props([
    'user',
    'reportTypes',
    'previewData',
    'filterOptions' => [],
])

@php
    // Static option lists shared by every role; role-specific lists
    // (destination/category/municipality) come in via $filterOptions and
    // are simply absent where a role has no matching report type.
    $filterMeta = [
        'classification' => ['label' => 'Tourist Classification', 'plural' => 'Classifications', 'options' => ['Foreign', 'Domestic (Other Province)', 'Local (Same Province)']],
        'gender' => ['label' => 'Gender', 'plural' => 'Genders', 'options' => ['Male', 'Female']],
        'sentiment' => ['label' => 'Sentiment', 'plural' => 'Sentiments', 'options' => ['Positive', 'Neutral', 'Negative']],
        'destination' => ['label' => 'Destination', 'plural' => 'Destinations', 'options' => $filterOptions['destination'] ?? []],
        'category' => ['label' => 'Category', 'plural' => 'Categories', 'options' => $filterOptions['category'] ?? []],
        'municipality' => ['label' => 'Municipality', 'plural' => 'Municipalities', 'options' => $filterOptions['municipality'] ?? []],
    ];
    $activeFilterKeys = collect($reportTypes)->flatMap(fn ($type) => $type['filters'])->unique()->values();
@endphp

{{-- SECTION 1: Report Generator — one centralized area, not a modal, so the
     whole select-type → pick-period → filters → generate → preview flow
     stays visible on one page (no step is hidden behind a popup). --}}
<div class="rounded-md border border-sand-200 bg-sand-0 p-5">
    <h2 class="font-display text-base font-bold text-sand-900">Report Generator</h2>
    <p class="mt-1 text-sm text-sand-600">Select a report type and reporting period, then generate.</p>

    <form id="report-generator-form" class="mt-4 flex flex-col gap-4" onsubmit="return false">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Report Type <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="report-type-select" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    @foreach ($reportTypes as $type)
                        <option value="{{ $type['key'] }}" data-filters="{{ implode(',', $type['filters']) }}" data-description="{{ $type['description'] }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <p id="report-type-description" class="mt-1 text-xs text-sand-500"></p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Start Date <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="report-from" type="date" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">End Date <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="report-to" type="date" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
        </div>

        @if ($activeFilterKeys->isNotEmpty())
            <div id="report-filters-section" class="hidden">
                <p class="mb-1.5 text-xs font-semibold text-sand-700">Additional Filters</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ($activeFilterKeys as $key)
                        <div data-report-filter="{{ $key }}" class="hidden">
                            <label class="mb-1 block text-xs font-medium text-sand-600">{{ $filterMeta[$key]['label'] }}</label>
                            <select class="w-full rounded-sm border border-sand-300 bg-sand-50 px-3 py-2 text-sm text-sand-700">
                                <option value="">All {{ $filterMeta[$key]['plural'] }}</option>
                                @foreach ($filterMeta[$key]['options'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- SECTION 6: Error state — real validation feedback, shown near
             the fields it concerns rather than only as a toast. --}}
        <div id="report-generator-error" class="hidden rounded-sm bg-danger-bg px-3.5 py-2.5 text-sm text-danger" role="alert"></div>

        <div>
            <button
                type="button"
                id="report-generate-button"
                class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900 disabled:cursor-not-allowed disabled:opacity-70"
            >
                <i class="ti ti-file-report" data-generate-icon aria-hidden="true"></i>
                <span data-generate-label>Generate Report</span>
            </button>
        </div>
    </form>
</div>

{{-- SECTION 7: Success state. Only a "View Report" shortcut lives here —
     export actions live in exactly one place (Report Actions, at the end
     of the preview) so there is never more than one set of download
     buttons on the page. --}}
<div id="report-success-banner" class="mt-4 hidden flex flex-col items-start justify-between gap-3 rounded-sm bg-success-bg px-4 py-3 text-sm text-success sm:flex-row sm:items-center">
    <span class="flex items-center gap-2"><i class="ti ti-circle-check" aria-hidden="true"></i>Report generated successfully.</span>
    <button type="button" data-preview-scroll class="rounded-sm border border-success/30 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-success hover:border-success/60">View Report</button>
</div>

{{-- SECTION 2: Report Preview — hidden until a report has been generated
     or viewed from Recent Reports. Every type's summary/table/chart is
     pre-rendered (reusing the same shared components used elsewhere in the
     app) and toggled by JS, so no report content is ever built from raw
     strings in JavaScript. --}}
<div id="report-preview" class="mt-6 hidden">
    <h2 class="font-display text-base font-bold text-sand-900">Report Preview</h2>

    <div id="report-preview-card" class="mt-3 rounded-md border border-sand-200 bg-sand-0 p-6 sm:p-8">
        <div class="flex flex-col items-start justify-between gap-4 border-b border-sand-200 pb-5 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <x-logo class="text-lg" />
                <div>
                    <p class="font-display text-sm font-bold text-sand-900">{{ $user->organization_name }}</p>
                    <p class="text-xs text-sand-500">{{ $user->organization_subtitle }}</p>
                </div>
            </div>
            <p class="text-xs text-sand-500">Generated <span data-preview-generated-date></span> by <span data-preview-generated-by>{{ $user->name }}</span></p>
        </div>

        <div class="mt-5">
            <p class="text-xs font-semibold tracking-widest text-sand-500 uppercase">Tourism Report</p>
            <h3 class="mt-1 font-display text-xl font-bold text-sand-900" data-preview-title></h3>
            <p class="mt-1 text-sm text-sand-600">Reporting Period: <span data-preview-period></span></p>
            <p class="text-sm text-sand-600">Coverage: {{ $user->organization_subtitle }}</p>
        </div>

        @foreach ($reportTypes as $type)
            @php $panel = $previewData[$type['key']] ?? null; @endphp
            @if ($panel)
                <div data-preview-panel="{{ $type['key'] }}" class="mt-5 hidden">
                    @if ($panel['empty'])
                        {{-- SECTION 15 (EMPTY): distinct from "no reports generated
                             yet" — this means a report WAS generated but there is
                             nothing to show for the selected scope/period. --}}
                        <x-dashboard.empty-state
                            icon="ti-file-off"
                            title="No report data available for the selected period."
                        />
                    @else
                        @if (count($panel['summary']))
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($panel['summary'] as $stat)
                                    <x-dashboard.kpi-card :label="$stat['label']" :value="$stat['value']" />
                                @endforeach
                            </div>
                        @endif

                        @if ($panel['chart'])
                            <div class="mt-5">
                                @if ($panel['chart']['type'] === 'bar')
                                    @php $maxValue = collect($panel['chart']['items'])->max('value') ?: 1; @endphp
                                    <p class="mb-2 text-xs font-semibold tracking-widest text-sand-500 uppercase">{{ $panel['chart']['title'] }}</p>
                                    <div class="flex flex-col gap-2.5">
                                        @foreach ($panel['chart']['items'] as $item)
                                            <div>
                                                <div class="mb-1 flex items-center justify-between text-xs text-sand-600">
                                                    <span>{{ $item['label'] }}</span>
                                                    <span class="font-semibold text-sand-800">{{ number_format($item['value']) }}</span>
                                                </div>
                                                <div class="h-2.5 rounded-full bg-sand-100">
                                                    <div class="h-2.5 rounded-full bg-primary-700" style="width: {{ round(($item['value'] / $maxValue) * 100) }}%"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif ($panel['chart']['type'] === 'trend')
                                    <p class="mb-2 text-xs font-semibold tracking-widest text-sand-500 uppercase">{{ $panel['chart']['title'] }}</p>
                                    <div class="rounded-md border border-sand-200 p-4">
                                        <svg data-trend-chart-static data-values="{{ json_encode($panel['chart']['values']) }}" viewBox="0 0 460 140" class="h-32 w-full"></svg>
                                        <div class="mt-2 flex justify-between text-[11px] text-sand-500">
                                            @foreach ($panel['chart']['labels'] as $label)
                                                <span>{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif ($panel['chart']['type'] === 'donut')
                                    @php
                                        $chartTotal = $panel['chart']['positive'] + $panel['chart']['neutral'] + $panel['chart']['negative'] ?: 1;
                                        $positivePct = round(($panel['chart']['positive'] / $chartTotal) * 100);
                                    @endphp
                                    <p class="mb-2 text-xs font-semibold tracking-widest text-sand-500 uppercase">Sentiment Distribution</p>
                                    <div class="flex items-center gap-5">
                                        <x-dashboard.donut-chart
                                            :segments="[
                                                ['label' => 'Positive', 'value' => $panel['chart']['positive'], 'color' => 'var(--color-success)'],
                                                ['label' => 'Neutral', 'value' => $panel['chart']['neutral'], 'color' => 'var(--color-warning)'],
                                                ['label' => 'Negative', 'value' => $panel['chart']['negative'], 'color' => 'var(--color-danger)'],
                                            ]"
                                            :center-label="$positivePct.'%'"
                                            center-sublabel="Positive"
                                        />
                                        <div class="flex flex-col gap-2 text-xs">
                                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success"></span>Positive <b class="ml-auto font-semibold">{{ number_format($panel['chart']['positive']) }}</b></span>
                                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-warning"></span>Neutral <b class="ml-auto font-semibold">{{ number_format($panel['chart']['neutral']) }}</b></span>
                                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger"></span>Negative <b class="ml-auto font-semibold">{{ number_format($panel['chart']['negative']) }}</b></span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($panel['breakdown'])
                            <div class="mt-5">
                                <p class="mb-2 text-xs font-semibold tracking-widest text-sand-500 uppercase">Breakdown by {{ $panel['breakdown']['label'] }}</p>
                                <div class="overflow-x-auto rounded-md border border-sand-200">
                                    <table class="w-full min-w-[480px] border-collapse text-sm">
                                        <thead>
                                            <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                                @foreach ($panel['breakdown']['columns'] as $column)
                                                    <th class="px-4 py-3">{{ $column }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-sand-100">
                                            @foreach ($panel['breakdown']['rows'] as $row)
                                                <tr>
                                                    @foreach ($row as $cell)
                                                        <td class="px-4 py-3 text-sand-700">{{ $cell }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if (count($panel['columns']))
                            <div class="mt-5">
                                <p class="mb-2 text-xs font-semibold tracking-widest text-sand-500 uppercase">Detailed Records</p>

                                @if ($panel['filterable'])
                                    <div data-filterable-table data-page-size="8">
                                        <div class="mb-3 flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2 print:hidden">
                                            <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                                            <input data-filter-input type="search" placeholder="Search records..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                                        </div>
                                        <div class="overflow-x-auto rounded-md border border-sand-200">
                                            <table class="w-full min-w-[560px] border-collapse text-sm">
                                                <thead>
                                                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                                        @foreach ($panel['columns'] as $column)
                                                            <th class="px-4 py-3">{{ $column }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-sand-100">
                                                    @foreach ($panel['rows'] as $row)
                                                        <tr data-row data-search-text="{{ Str::lower(implode(' ', $row)) }}">
                                                            @foreach ($row as $cell)
                                                                <td class="px-4 py-3 text-sand-700">{{ $cell }}</td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div data-pagination class="mt-3 flex items-center justify-center gap-1 print:hidden"></div>
                                    </div>
                                @else
                                    <div class="overflow-x-auto rounded-md border border-sand-200">
                                        <table class="w-full min-w-[480px] border-collapse text-sm">
                                            <thead>
                                                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                                    @foreach ($panel['columns'] as $column)
                                                        <th class="px-4 py-3">{{ $column }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-sand-100">
                                                @foreach ($panel['rows'] as $row)
                                                    <tr>
                                                        @foreach ($row as $cell)
                                                            <td class="px-4 py-3 text-sand-700">{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    {{-- SECTION 11: Report Actions — one clearly grouped area, view-first
         export-second. No other download/print buttons exist anywhere
         else on this page. --}}
    <div class="mt-3 flex flex-wrap gap-2 print:hidden">
        <button type="button" id="report-print-button" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
            <i class="ti ti-printer" aria-hidden="true"></i> Print
        </button>
        <button type="button" data-report-download="pdf" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
            <i class="ti ti-file-type-pdf" aria-hidden="true"></i> Download PDF
        </button>
        <button type="button" data-report-download="excel" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
            <i class="ti ti-file-type-xls" aria-hidden="true"></i> Export Excel
        </button>
    </div>

    <style media="print">
        body * { visibility: hidden; }
        #report-preview-card, #report-preview-card * { visibility: visible; }
        #report-preview-card { position: fixed; inset: 0; border: none; }
    </style>
</div>
