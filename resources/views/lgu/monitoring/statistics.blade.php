@php
    $trendLabels = ['week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
    $totalVisitors = $classificationBreakdown->sum();
    $maxEstablishment = $establishmentComparison->max() ?: 1;
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Visitation Statistics"
        description="Visitor trends and classification for {{ $municipality }}."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.kpi-card label="Total Visitors (period)" :value="number_format($totalVisitors)" delta="Across all classifications" tone="neutral" />
        <x-dashboard.kpi-card :label="$summary[1]['label']" :value="$summary[1]['value']" :delta="$summary[1]['delta']" :tone="$summary[1]['tone']" />
        <x-dashboard.kpi-card :label="$summary[2]['label']" :value="$summary[2]['value']" :delta="$summary[2]['delta']" :tone="$summary[2]['tone']" />
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-bold text-sand-900">Monthly Tourist Trends</h2>
                <p class="text-xs text-sand-500">Tourist arrival trend for {{ $municipality }}</p>
            </div>
            <div class="flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1 text-xs font-semibold">
                @foreach ($trendLabels as $period => $label)
                    <button
                        type="button"
                        data-trend-period="{{ $period }}"
                        data-trend-target="lgu-statistics-trend"
                        @class(['rounded-sm px-2.5 py-1.5 transition-colors', 'bg-sand-0 shadow-sm text-primary-700' => $period === 'year', 'text-sand-600' => $period !== 'year'])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-4" id="lgu-statistics-trend" data-trend-chart="lgu-statistics-trend-data">
            <svg class="h-44 w-full" preserveAspectRatio="none"></svg>
            <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
        </div>
        <script type="application/json" id="lgu-statistics-trend-data">@json($arrivalTrend)</script>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Visitor Classification</h2>
            <p class="text-xs text-sand-500">Share of visitors by classification</p>

            @if ($totalVisitors)
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($classificationBreakdown as $classification => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-44 shrink-0 truncate text-sm text-sand-700">{{ $classification }}</span>
                            <div class="h-2.5 flex-1 rounded-full bg-sand-100">
                                <div class="h-2.5 rounded-full bg-primary-700" style="width: {{ max(4, round(($count / $totalVisitors) * 100)) }}%"></div>
                            </div>
                            <span class="w-12 shrink-0 text-right text-sm font-semibold text-sand-800">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-sand-500">No arrival data recorded yet.</p>
            @endif
        </div>

        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Establishment Comparison</h2>
            <p class="text-xs text-sand-500">Visitors logged per establishment</p>

            @if ($establishmentComparison->isNotEmpty())
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($establishmentComparison as $establishment => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-44 shrink-0 truncate text-sm text-sand-700">{{ $establishment }}</span>
                            <div class="h-2.5 flex-1 rounded-full bg-sand-100">
                                <div class="h-2.5 rounded-full bg-secondary-500" style="width: {{ max(4, round(($count / $maxEstablishment) * 100)) }}%"></div>
                            </div>
                            <span class="w-12 shrink-0 text-right text-sm font-semibold text-sand-800">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-sand-500">No establishment data recorded yet.</p>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
