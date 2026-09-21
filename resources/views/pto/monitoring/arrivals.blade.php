@php
    $rowMunicipalities = collect($arrivals)->pluck('municipality')->unique()->sort()->values();
    $rowEstablishments = collect($arrivals)->pluck('establishment')->unique()->sort()->values();
    $classifications = collect($arrivals)->pluck('classification')->unique()->sort()->values();

    $trendLabels = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
    $maxVisits = collect($municipalityComparison)->max('visits') ?: 1;
    $totalVisits = collect($municipalityComparison)->sum('visits');

    $perfMunicipalities = collect($performance)->pluck('municipality')->unique()->sort()->values();
    $trendIcon = fn ($trend) => match ($trend) {
        'up' => ['ti-trending-up', 'text-success'],
        'down' => ['ti-trending-down', 'text-danger'],
        default => ['ti-minus', 'text-sand-500'],
    };

    $tabs = [
        'arrivals' => 'Arrivals',
        'statistics' => 'Visitation Statistics',
        'destinations' => 'Destination Performance',
    ];
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourism Monitoring"
        description="Arrival records, visitation trends, and destination performance across the province."
    >
        <x-slot:actions>
            <a href="{{ route('pto.reports') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-file-report" aria-hidden="true"></i>
                Generate Report
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-tabs class="mt-4 flex border-b border-sand-200">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                data-tab-target="{{ $key }}"
                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                @class([
                    'border-b-2 px-4 py-3 text-sm font-semibold transition-colors',
                    'border-primary-700 text-primary-700' => $activeTab === $key,
                    'border-transparent text-sand-500' => $activeTab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- Arrivals tab --}}
    <div data-tab-panel="arrivals" @class(['hidden' => $activeTab !== 'arrivals'])>
        <div data-filterable-table data-page-size="10" class="mt-6">
            <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
                <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                    <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                    <input data-filter-input type="search" placeholder="Search by establishment or municipality..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                </div>

                <select data-filter-select data-filter-key="municipality" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                    <option value="">All Municipalities</option>
                    @foreach ($rowMunicipalities as $m)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </select>

                <select data-filter-select data-filter-key="establishment" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                    <option value="">All Establishments</option>
                    @foreach ($rowEstablishments as $e)
                        <option value="{{ $e }}">{{ $e }}</option>
                    @endforeach
                </select>

                <select data-filter-select data-filter-key="classification" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                    <option value="">All Classifications</option>
                    @foreach ($classifications as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>

                <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                    Reset
                </button>
            </div>

            <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($arrivals) }}</span> of {{ count($arrivals) }} records</p>

            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[760px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Establishment</th>
                            <th class="px-4 py-3">Municipality</th>
                            <th class="px-4 py-3">Classification</th>
                            <th class="px-4 py-3">Gender</th>
                            <th class="px-4 py-3 text-right">Visitors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($arrivals as $row)
                            <tr
                                data-row
                                data-municipality="{{ $row['municipality'] }}"
                                data-establishment="{{ $row['establishment'] }}"
                                data-classification="{{ $row['classification'] }}"
                                data-search-text="{{ strtolower($row['establishment'].' '.$row['municipality'].' '.$row['classification']) }}"
                                class="hover:bg-sand-50"
                            >
                                <td class="px-4 py-3 text-sand-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $row['establishment'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['municipality'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['classification'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['gender'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $row['visitors'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-dashboard.empty-state
                data-empty-state
                class="hidden mt-3"
                icon="ti-map-search"
                title="No arrival records match your filters"
                description="Try widening your municipality, establishment, or classification selection."
            />

            <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
        </div>
    </div>

    {{-- Visitation Statistics tab --}}
    <div data-tab-panel="statistics" @class(['hidden' => $activeTab !== 'statistics'])>
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
    </div>

    {{-- Destination Performance tab --}}
    <div data-tab-panel="destinations" @class(['hidden' => $activeTab !== 'destinations'])>
        <div data-filterable-table data-page-size="10" class="mt-6">
            <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 sm:flex-row sm:items-center">
                <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                    <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                    <input data-filter-input type="search" placeholder="Search destinations..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                </div>
                <select data-filter-select data-filter-key="municipality" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                    <option value="">All Municipalities</option>
                    @foreach ($perfMunicipalities as $m)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[560px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Destination</th>
                            <th class="px-4 py-3">Municipality</th>
                            <th class="px-4 py-3 text-right">Visits</th>
                            <th class="px-4 py-3 text-right">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($performance as $row)
                            @php [$icon, $color] = $trendIcon($row['trend']); @endphp
                            <tr data-row data-municipality="{{ $row['municipality'] }}" data-search-text="{{ strtolower($row['destination'].' '.$row['municipality']) }}" class="hover:bg-sand-50">
                                <td class="px-4 py-3 text-sand-500">{{ $row['rank'] }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $row['destination'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['municipality'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ number_format($row['visits']) }}</td>
                                <td class="px-4 py-3 text-right {{ $color }}"><i class="ti {{ $icon }}" aria-hidden="true"></i></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>

            <x-dashboard.empty-state
                data-empty-state
                class="hidden mt-3"
                icon="ti-map-search"
                title="No destinations match your filters"
                description="Try a different municipality or search term."
            />
        </div>
    </div>
</x-layouts.dashboard>
