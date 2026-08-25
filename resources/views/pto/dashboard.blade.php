@php
    $trendLabels = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
    $sentimentTotal = array_sum($sentiment);
    $positivePct = round(($sentiment['positive'] / max($sentimentTotal, 1)) * 100);
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Provincial Tourism Dashboard"
        description="Consolidated tourist arrivals, reporting status, and tourist experience across the 11 municipalities of Davao Oriental."
    >
        <x-slot:actions>
            <a href="{{ route('pto.reports') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-file-report" aria-hidden="true"></i>
                Open Reports
            </a>
            <a href="{{ route('pto.feedback.analytics') }}" class="inline-flex items-center gap-2 rounded-sm bg-accent-500 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-accent-600">
                <i class="ti ti-heart-handshake" aria-hidden="true"></i>
                Tourist Experience Analytics
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    {{-- Summary Cards --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
                    <p class="text-xs text-sand-500">Monitoring how many tourists arrive and how that changes</p>
                </div>
                <div class="flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1 text-xs font-semibold">
                    @foreach ($trendLabels as $period => $label)
                        <button
                            type="button"
                            data-trend-period="{{ $period }}"
                            data-trend-target="dashboard-trend"
                            @class(['rounded-sm px-2.5 py-1.5 transition-colors', 'bg-sand-0 shadow-sm text-primary-700' => $period === 'month', 'text-sand-600' => $period !== 'month'])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mt-4" id="dashboard-trend" data-trend-chart="dashboard-trend-data">
                <svg class="h-36 w-full" preserveAspectRatio="none"></svg>
                <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
            </div>
            <script type="application/json" id="dashboard-trend-data">@json($arrivalTrend)</script>
        </div>

        {{-- Tourist Experience Overview --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Tourist Experience Overview</h2>
            <p class="text-xs text-sand-500">{{ number_format($sentimentTotal) }} feedback entries analyzed</p>

            <div class="mt-4 flex items-center gap-5">
                <x-dashboard.donut-chart
                    :segments="[
                        ['label' => 'Positive', 'value' => $sentiment['positive'], 'color' => 'var(--color-success)'],
                        ['label' => 'Neutral', 'value' => $sentiment['neutral'], 'color' => 'var(--color-warning)'],
                        ['label' => 'Negative', 'value' => $sentiment['negative'], 'color' => 'var(--color-danger)'],
                    ]"
                    :center-label="$positivePct.'%'"
                    center-sublabel="Positive"
                />
                <div class="flex flex-col gap-2 text-xs">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success"></span>Positive <b class="ml-auto font-semibold">{{ number_format($sentiment['positive']) }}</b></span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-warning"></span>Neutral <b class="ml-auto font-semibold">{{ number_format($sentiment['neutral']) }}</b></span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger"></span>Negative <b class="ml-auto font-semibold">{{ number_format($sentiment['negative']) }}</b></span>
                </div>
            </div>
            <a href="{{ route('pto.feedback.analytics') }}" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">
                View Experience Analytics <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Destination Performance --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base font-bold text-sand-900">Destination Performance</h2>
                <a href="{{ route('pto.monitoring.destinations') }}" class="text-xs font-semibold text-primary-700 hover:text-primary-900">View all</a>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[420px] text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs font-semibold text-sand-500 uppercase">
                            <th class="py-2 pr-2">#</th>
                            <th class="py-2 pr-2">Destination</th>
                            <th class="py-2 pr-2">Municipality</th>
                            <th class="py-2 pr-2 text-right">Visits</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($destinationPerformance as $row)
                            <tr>
                                <td class="py-2 pr-2 text-sand-500">{{ $row['rank'] }}</td>
                                <td class="py-2 pr-2 font-medium text-sand-900">{{ $row['destination'] }}</td>
                                <td class="py-2 pr-2 text-sand-600">{{ $row['municipality'] }}</td>
                                <td class="py-2 pr-2 text-right font-semibold text-sand-800">{{ number_format($row['visits']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Recent Activity</h2>
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
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Quick Actions</h2>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['icon' => 'ti-users', 'label' => 'View Tourist Arrivals', 'href' => route('pto.monitoring.arrivals')],
                ['icon' => 'ti-list-details', 'label' => 'View Tourism Directory', 'href' => route('pto.directory.destinations')],
                ['icon' => 'ti-message-2', 'label' => 'View Feedback', 'href' => route('pto.feedback.index')],
                ['icon' => 'ti-file-report', 'label' => 'Generate Report', 'href' => route('pto.reports')],
            ] as $action)
                <a href="{{ $action['href'] }}" class="flex items-center gap-2.5 rounded-md border border-sand-200 px-3.5 py-3 text-sm font-semibold text-sand-800 transition-colors hover:border-primary-300 hover:text-primary-700">
                    <i class="ti {{ $action['icon'] }} text-primary-700" aria-hidden="true"></i>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
