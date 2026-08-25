@php
    $rowEstablishments = collect($arrivals)->pluck('establishment')->unique()->sort()->values();
    $classifications = collect($arrivals)->pluck('classification')->unique()->sort()->values();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Tourist Arrivals"
        description="Arrival records submitted by establishments in {{ $municipality }}. Establishments remain responsible for filing their own arrival data."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.reports') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-file-report" aria-hidden="true"></i>
                Generate Report
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="10" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by establishment..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>

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

        @if (count($arrivals))
            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[620px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Establishment</th>
                            <th class="px-4 py-3">Classification</th>
                            <th class="px-4 py-3">Gender</th>
                            <th class="px-4 py-3 text-right">Visitors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($arrivals as $row)
                            <tr
                                data-row
                                data-establishment="{{ $row['establishment'] }}"
                                data-classification="{{ $row['classification'] }}"
                                data-search-text="{{ strtolower($row['establishment'].' '.$row['classification']) }}"
                                class="hover:bg-sand-50"
                            >
                                <td class="px-4 py-3 text-sand-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $row['establishment'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['classification'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['gender'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $row['visitors'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-dashboard.empty-state
            data-empty-state
            class="{{ count($arrivals) ? 'hidden' : '' }} mt-3"
            icon="ti-map-search"
            title="No arrival records for {{ $municipality }} yet"
            description="Once establishments in your municipality start filing arrivals, they'll show up here."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
