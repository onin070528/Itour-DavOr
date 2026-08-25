@php
    $trendLabels = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
    $maxVisits = collect($municipalityComparison)->max('visits') ?: 1;
    $totalVisits = collect($municipalityComparison)->sum('visits');
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Visitation Statistics"
        description="Province-wide visitor trends and how municipalities compare against each other."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.kpi-card label="Total Visitors (YTD)" value="{{ number_format($totalVisits) }}" delta="Across 11 municipalities" tone="neutral" />
        <x-dashboard.kpi-card :label="$summary[1]['label']" :value="$summary[1]['value']" :delta="$summary[1]['delta']" :tone="$summary[1]['tone']" />
        <x-dashboard.kpi-card label="Top Municipality" :value="collect($municipalityComparison)->first()['municipality']" delta="Leading in visits this year" tone="success" />
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-bold text-sand-900">Monthly Visitor Trends</h2>
                <p class="text-xs text-sand-500">Province-wide tourist arrival trend</p>
            </div>
            <div class="flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1 text-xs font-semibold">
                @foreach ($trendLabels as $period => $label)
                    <button
                        type="button"
                        data-trend-period="{{ $period }}"
                        data-trend-target="statistics-trend"
                        @class(['rounded-sm px-2.5 py-1.5 transition-colors', 'bg-sand-0 shadow-sm text-primary-700' => $period === 'year', 'text-sand-600' => $period !== 'year'])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-4" id="statistics-trend" data-trend-chart="statistics-trend-data">
            <svg class="h-44 w-full" preserveAspectRatio="none"></svg>
            <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
        </div>
        <script type="application/json" id="statistics-trend-data">@json($arrivalTrend)</script>
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Municipality Comparison</h2>
        <p class="text-xs text-sand-500">Total visitor arrivals by municipality (year to date)</p>

        <div class="mt-4 flex flex-col gap-3">
            @foreach ($municipalityComparison as $row)
                <div class="flex items-center gap-3">
                    <span class="w-36 shrink-0 truncate text-sm text-sand-700">{{ $row['municipality'] }}</span>
                    <div class="h-2.5 flex-1 rounded-full bg-sand-100">
                        <div class="h-2.5 rounded-full bg-primary-700" style="width: {{ max(3, round(($row['visits'] / $maxVisits) * 100)) }}%"></div>
                    </div>
                    <span class="w-20 shrink-0 text-right text-sm font-semibold text-sand-800">{{ number_format($row['visits']) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
