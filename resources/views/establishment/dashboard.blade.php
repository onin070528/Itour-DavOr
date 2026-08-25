@php
    $trendLabels = ['week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
    $totalClassified = $classificationBreakdown->sum();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="{{ $establishmentName }}"
        description="How your establishment is performing on iTOUR."
    >
        <x-slot:actions>
            <a href="{{ route('establishment.arrivals.record') }}" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-send" aria-hidden="true"></i>
                Record Tourist Arrival
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    {{-- Summary Cards --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($summary as $card)
            <x-dashboard.kpi-card :label="$card['label']" :value="$card['value']" :delta="$card['delta']" :tone="$card['tone']" />
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Tourist Arrival Overview --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-bold text-sand-900">Tourist Arrival Overview</h2>
                    <p class="text-xs text-sand-500">Arrivals recorded at {{ $establishmentName }}</p>
                </div>
                <div class="flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1 text-xs font-semibold">
                    @foreach ($trendLabels as $period => $label)
                        <button
                            type="button"
                            data-trend-period="{{ $period }}"
                            data-trend-target="est-dashboard-trend"
                            @class(['rounded-sm px-2.5 py-1.5 transition-colors', 'bg-sand-0 shadow-sm text-primary-700' => $period === 'month', 'text-sand-600' => $period !== 'month'])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mt-4" id="est-dashboard-trend" data-trend-chart="est-dashboard-trend-data">
                <svg class="h-36 w-full" preserveAspectRatio="none"></svg>
                <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
            </div>
            <script type="application/json" id="est-dashboard-trend-data">@json($arrivalTrend)</script>
        </div>

        {{-- Tourist Experience Overview --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Tourist Experience Overview</h2>
            @php $sentimentTotal = array_sum($sentiment); @endphp
            <p class="text-xs text-sand-500">{{ $sentimentTotal }} feedback entries</p>

            @if ($sentimentTotal)
                <div class="mt-4 flex items-center gap-5">
                    <x-dashboard.donut-chart
                        :segments="[
                            ['label' => 'Positive', 'value' => $sentiment['positive'], 'color' => 'var(--color-success)'],
                            ['label' => 'Neutral', 'value' => $sentiment['neutral'], 'color' => 'var(--color-warning)'],
                            ['label' => 'Negative', 'value' => $sentiment['negative'], 'color' => 'var(--color-danger)'],
                        ]"
                        :center-label="round(($sentiment['positive'] / $sentimentTotal) * 100).'%'"
                        center-sublabel="Positive"
                    />
                    <div class="flex flex-col gap-2 text-xs">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success"></span>Positive <b class="ml-auto font-semibold">{{ $sentiment['positive'] }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-warning"></span>Neutral <b class="ml-auto font-semibold">{{ $sentiment['neutral'] }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger"></span>Negative <b class="ml-auto font-semibold">{{ $sentiment['negative'] }}</b></span>
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-sand-500">No feedback recorded yet.</p>
            @endif

            <a href="{{ route('establishment.feedback.analytics') }}" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">
                View Experience Analytics <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Visitor Classification --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
            <h2 class="font-display text-base font-bold text-sand-900">Visitor Classification</h2>
            <p class="text-xs text-sand-500">Recorded arrivals by tourist classification</p>

            @if ($totalClassified)
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($classificationBreakdown as $classification => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-44 shrink-0 truncate text-sm text-sand-700">{{ $classification }}</span>
                            <div class="h-2.5 flex-1 rounded-full bg-sand-100">
                                <div class="h-2.5 rounded-full bg-primary-700" style="width: {{ max(4, round(($count / $totalClassified) * 100)) }}%"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right text-sm font-semibold text-sand-800">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-sand-500">No arrivals recorded yet. Use Record Tourist Arrival to log your first guest.</p>
            @endif
        </div>

        {{-- Recent Activity --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Recent Activity</h2>
            @if (count($recentActivity))
                <ul class="mt-3 flex flex-col gap-3">
                    @foreach ($recentActivity as $activity)
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                <i class="ti {{ $activity['icon'] }} text-sm" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-sand-900">{{ $activity['title'] }}</p>
                                <p class="text-xs text-sand-500">{{ $activity['description'] }}</p>
                                <p class="mt-0.5 text-[10px] text-sand-400">{{ $activity['time'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-sand-500">No recent activity yet.</p>
            @endif
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Quick Actions</h2>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['icon' => 'ti-send', 'label' => 'Record Tourist Arrival', 'href' => route('establishment.arrivals.record')],
                ['icon' => 'ti-list-details', 'label' => 'View Arrival Records', 'href' => route('establishment.arrivals.index')],
                ['icon' => 'ti-building-store', 'label' => 'View My Establishment', 'href' => route('establishment.profile')],
                ['icon' => 'ti-message-2', 'label' => 'View Feedback', 'href' => route('establishment.feedback.index')],
                ['icon' => 'ti-file-report', 'label' => 'Generate Report', 'href' => route('establishment.reports')],
            ] as $action)
                <a href="{{ $action['href'] }}" class="flex items-center gap-2.5 rounded-md border border-sand-200 px-3.5 py-3 text-sm font-semibold text-sand-800 transition-colors hover:border-primary-300 hover:text-primary-700">
                    <i class="ti {{ $action['icon'] }} text-primary-700" aria-hidden="true"></i>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
