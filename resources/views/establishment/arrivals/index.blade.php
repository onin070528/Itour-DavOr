@php
    $statusTone = fn ($status) => $status === 'Recorded' ? 'success' : 'warning';
    $classifications = collect($arrivals)->pluck('classification')->unique()->sort()->values();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Arrival Records"
        description="Guest arrivals recorded for {{ $establishmentName }}."
    >
        <x-slot:actions>
            <a href="{{ route('establishment.arrivals.record') }}" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Record Arrival
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by visitor name or remarks..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
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
                <table class="w-full min-w-[760px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Visitor Name</th>
                            <th class="px-4 py-3">Gender</th>
                            <th class="px-4 py-3">Classification</th>
                            <th class="px-4 py-3">Remarks</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($arrivals as $row)
                            <tr
                                data-row
                                data-classification="{{ $row['classification'] }}"
                                data-search-text="{{ strtolower(($row['visitorName'] ?? '').' '.($row['remarks'] ?? '')) }}"
                                class="hover:bg-sand-50"
                            >
                                <td class="px-4 py-3 text-sand-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $row['visitorName'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['gender'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['classification'] }}</td>
                                <td class="px-4 py-3 text-sand-600">{{ $row['remarks'] ?? '—' }}</td>
                                <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
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
            title="No arrivals recorded yet"
            description="Use Record Arrival to log your first guest."
        >
            <x-slot:action>
                <a href="{{ route('establishment.arrivals.record') }}" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    Record Arrival
                </a>
            </x-slot:action>
        </x-dashboard.empty-state>

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
