@php
    $hasTrendData = collect($arrivalTrend['month'])->sum('value') > 0 || collect($arrivalTrend['year'])->sum('value') > 0;
    $classificationTotal = $classification['local'] + $classification['foreign'];
    $genderTotal = $classification['male'] + $classification['female'];
    $statusTone = fn (string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        'For Clarification' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourism Monitoring Dashboard"
        description="Verified tourist arrivals, visitation statistics, and reporting status across the municipalities of Davao Oriental."
    />

    {{-- Filters: every section below respects these, applied server-side.
         Only Verified reports ever count toward a total — see
         App\Support\TourismAnalytics. --}}
    <form method="GET" action="{{ route('pto.dashboard') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <div>
            <label for="year" class="mb-1 block text-xs font-semibold text-sand-700">Year</label>
            <select id="year" name="year" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($yearOptions as $year)
                    <option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="month" class="mb-1 block text-xs font-semibold text-sand-700">Month</label>
            <select id="month" name="month" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                <option value="">Whole Year</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($filters['month'] === $m)>{{ \Carbon\CarbonImmutable::create(2000, $m, 1)->format('F') }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label for="municipality_id" class="mb-1 block text-xs font-semibold text-sand-700">LGU / Municipality</label>
            <select id="municipality_id" name="municipality_id" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                <option value="">All Municipalities</option>
                @foreach ($municipalities as $municipality)
                    <option value="{{ $municipality->id }}" @selected($filters['municipalityId'] === $municipality->id)>{{ $municipality->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="listing_id" class="mb-1 block text-xs font-semibold text-sand-700">Establishment</label>
            <select id="listing_id" name="listing_id" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                <option value="">All Establishments</option>
                @foreach ($establishments as $establishment)
                    <option value="{{ $establishment->id }}" @selected($filters['listingId'] === $establishment->id)>{{ $establishment->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="classification" class="mb-1 block text-xs font-semibold text-sand-700">Visitor Classification</label>
            <select id="classification" name="classification" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                <option value="">All Visitors</option>
                <option value="local" @selected($filters['classification'] === 'local')>Local</option>
                <option value="foreign" @selected($filters['classification'] === 'foreign')>Foreign</option>
            </select>
        </div>
        <a href="{{ route('pto.dashboard') }}" class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
            Reset Filters
        </a>
    </form>

    {{-- KPI Summary --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($kpis as $card)
            <x-dashboard.kpi-card :label="$card['label']" :value="$card['value']" :delta="$card['delta']" :tone="$card['tone']" :href="$card['href'] ?? null" />
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Tourist Arrival Trend --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-bold text-sand-900">Tourist Arrival Trend</h2>
                    <p class="text-xs text-sand-500">Verified arrivals over time — identify increases and decreases.</p>
                </div>
                @if ($hasTrendData)
                    <div class="flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1 text-xs font-semibold">
                        <button type="button" data-trend-period="month" data-trend-target="dashboard-trend" class="rounded-sm bg-sand-0 px-2.5 py-1.5 text-primary-700 shadow-sm transition-colors">{{ $filters['year'] }}</button>
                        <button type="button" data-trend-period="year" data-trend-target="dashboard-trend" class="rounded-sm px-2.5 py-1.5 text-sand-600 transition-colors">All Years</button>
                    </div>
                @endif
            </div>

            @if ($hasTrendData)
                <div class="mt-4" id="dashboard-trend" data-trend-chart="dashboard-trend-data">
                    <svg class="h-36 w-full" preserveAspectRatio="none"></svg>
                    <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
                </div>
                <script type="application/json" id="dashboard-trend-data">@json($arrivalTrend)</script>
            @else
                <div class="mt-4 flex h-36 items-center justify-center rounded-sm border border-dashed border-sand-300 text-center text-sm text-sand-500">
                    Trend unavailable — insufficient historical data.
                </div>
            @endif
        </div>

        {{-- Visitor Classification --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Visitor Classification</h2>
            <p class="text-xs text-sand-500">{{ number_format($classificationTotal) }} verified visitors classified</p>

            @if ($classificationTotal > 0)
                <div class="mt-4 flex items-center gap-5">
                    <x-dashboard.donut-chart
                        :segments="[
                            ['label' => 'Local', 'value' => $classification['local'], 'color' => 'var(--color-primary-700)'],
                            ['label' => 'Foreign', 'value' => $classification['foreign'], 'color' => 'var(--color-accent-500)'],
                        ]"
                        :center-label="$classificationTotal ? round(($classification['local'] / $classificationTotal) * 100).'%' : '—'"
                        center-sublabel="Local"
                    />
                    <div class="flex flex-col gap-2 text-xs">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary-700"></span>Local <b class="ml-auto font-semibold">{{ number_format($classification['local']) }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-accent-500"></span>Foreign <b class="ml-auto font-semibold">{{ number_format($classification['foreign']) }}</b></span>
                    </div>
                </div>

                @if ($genderTotal > 0)
                    <div class="mt-4 border-t border-sand-200 pt-4">
                        <p class="text-xs font-semibold text-sand-700">Male / Female</p>
                        <div class="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-sand-100">
                            <div class="h-full bg-primary-500" style="width: {{ round(($classification['male'] / $genderTotal) * 100) }}%"></div>
                            <div class="h-full bg-accent-300" style="width: {{ round(($classification['female'] / $genderTotal) * 100) }}%"></div>
                        </div>
                        <div class="mt-1.5 flex justify-between text-[10px] text-sand-500">
                            <span>Male {{ number_format($classification['male']) }}</span>
                            <span>Female {{ number_format($classification['female']) }}</span>
                        </div>
                    </div>
                @endif
            @else
                <p class="mt-4 text-sm text-sand-500">No verified tourism data available for the selected period.</p>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- LGU Visitation Statistics --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
            <h2 class="font-display text-base font-bold text-sand-900">LGU Visitation Statistics</h2>
            <p class="text-xs text-sand-500">Verified arrivals by municipality — select one to view its supporting reports.</p>

            @if ($municipalityComparison->isEmpty())
                <p class="mt-4 text-sm text-sand-500">No verified tourism data available for the selected period.</p>
            @else
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($municipalityComparison as $row)
                        <a href="{{ route('pto.monthlyReports.index', ['municipality_id' => $row['municipality']->id, 'period' => $filters['month'] ? \Carbon\CarbonImmutable::create($filters['year'], $filters['month'], 1)->format('Y-m') : null]) }}" class="block">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-sand-900">{{ $row['municipality']->name }}</span>
                                <span class="font-semibold text-sand-800">{{ number_format($row['total']) }}</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-sand-100">
                                <div class="h-full rounded-full bg-primary-700" style="width: {{ $row['percentage'] }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Activity --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Recent Activity</h2>
            @if ($recentActivity->isEmpty())
                <p class="mt-4 text-sm text-sand-500">No reporting activity yet.</p>
            @else
                <ul class="mt-3 flex flex-col gap-3">
                    @foreach ($recentActivity as $activity)
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                <i class="ti {{ $activity['icon'] }} text-sm" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-sand-900">{{ $activity['title'] }}</p>
                                <p class="text-xs text-sand-500">{{ $activity['description'] }}</p>
                                <p class="mt-0.5 text-[10px] text-sand-400">{{ $activity['time']->diffForHumans() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Reporting Status --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
            <h2 class="font-display text-base font-bold text-sand-900">LGU Reporting Status</h2>
            <p class="text-xs text-sand-500">A municipality with no report yet always shows as Not Submitted — never a zero.</p>

            @if ($notYetReportedCount > 0)
                <p class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-warning">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                    Official statistics above count only Verified reports — {{ $notYetReportedCount }} {{ Str::plural('LGU', $notYetReportedCount) }} {{ $notYetReportedCount === 1 ? 'has' : 'have' }} not yet reported for this period.
                </p>
            @endif

            @if ($reportingStatus->isEmpty())
                <x-dashboard.empty-state class="mt-3" icon="ti-map-pin-off" title="No municipalities to show" description="Try a different filter." />
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs font-semibold text-sand-500 uppercase">
                                <th class="py-2 pr-2">Municipality</th>
                                <th class="py-2 pr-2">Status</th>
                                <th class="py-2 pr-2">Date Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($reportingStatus as $row)
                                <tr>
                                    <td class="py-2 pr-2 font-medium text-sand-900">{{ $row['municipality']->name }}</td>
                                    <td class="py-2 pr-2"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                                    <td class="py-2 pr-2 text-sand-700">{{ $row['report']?->created_at?->format('M j, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Destination Performance — not yet available: destinations have
             no arrival-tracking link today, only establishments report
             arrivals. Shown honestly rather than faked. --}}
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Destination Performance</h2>
            <x-dashboard.empty-state
                class="mt-3"
                icon="ti-map-pin-off"
                title="Not available yet"
                description="Destination-level arrival tracking isn't connected yet — only establishments currently submit arrival reports."
            />
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Quick Actions</h2>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['icon' => 'ti-calendar-event', 'label' => 'Provincial Reports', 'href' => route('pto.monthlyReports.index')],
                ['icon' => 'ti-clipboard-check', 'label' => 'LGU Submissions', 'href' => route('pto.municipalReports.index')],
                ['icon' => 'ti-shield-check', 'label' => 'Audit Logs', 'href' => route('pto.auditLogs')],
            ] as $action)
                <a href="{{ $action['href'] }}" class="flex items-center gap-2.5 rounded-md border border-sand-200 px-3.5 py-3 text-sm font-semibold text-sand-800 transition-colors hover:border-primary-300 hover:text-primary-700">
                    <i class="ti {{ $action['icon'] }} text-primary-700" aria-hidden="true"></i>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
