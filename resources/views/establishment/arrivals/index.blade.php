{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Establishment Arrival Records page.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $statusTone = fn ($status) => $status === 'Recorded' ? 'success' : 'warning';
    $visitTypes = collect($arrivals)->pluck('visitType')->unique()->sort()->values();
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
                <input data-filter-input type="search" placeholder="Search by visitor name or origin..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="visit-type" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Visit Types</option>
                @foreach ($visitTypes as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($arrivals) }}</span> of {{ count($arrivals) }} records</p>

        @if (count($arrivals))
            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[760px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Visitor Name</th>
                            <th class="px-4 py-3">Visit Type</th>
                            <th class="px-4 py-3">Guests</th>
                            <th class="px-4 py-3">Origin</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($arrivals as $row)
                            <tr
                                data-row
                                data-visit-type="{{ $row['visitType'] }}"
                                data-search-text="{{ strtolower(($row['visitorName'] ?? '').' '.$row['origin']) }}"
                                class="hover:bg-sand-50"
                            >
                                <td class="px-4 py-3 text-sand-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y') }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $row['visitorName'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['visitType'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['guests'] }} <span class="text-xs text-sand-500">({{ $row['male'] }}M / {{ $row['female'] }}F)</span></td>
                                <td class="px-4 py-3 text-sand-600">{{ $row['origin'] }}</td>
                                <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" data-modal-open="arrival-view-modal-{{ $loop->index }}" class="inline-flex items-center gap-1.5 rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-700 hover:border-primary-300">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                        View
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($arrivals as $row)
                <x-dashboard.modal id="arrival-view-modal-{{ $loop->index }}" :title="'Arrival '.$row['id']">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        @php
                            $fields = [
                                'Date' => \Illuminate\Support\Carbon::parse($row['date'])->format('M j, Y'),
                                'Recorded At' => $row['recordedAt'] ?? '—',
                                'Visitor Name' => $row['visitorName'] ?? '—',
                                'Contact' => $row['contact'] ?? '—',
                                'Visit Type' => $row['visitType'],
                                'Recorded Via' => $row['source'],
                                'Total Guests' => $row['guests'],
                                'Male / Female' => $row['male'].' / '.$row['female'],
                                'Adults' => $row['adults'],
                                'Children' => $row['children'],
                                'Seniors' => $row['seniors'],
                                'Local Guests' => $row['local'],
                                'Local Origin' => trim(($row['localScope'] ?? '').($row['localPlace'] ? ' — '.$row['localPlace'] : '')) ?: '—',
                                'Foreign Guests' => $row['foreign'],
                                'Foreign Country' => $row['foreignCountry'] ?? '—',
                            ];
                        @endphp
                        @foreach ($fields as $label => $value)
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">{{ $label }}</dt><dd class="text-sand-800">{{ $value }}</dd></div>
                        @endforeach
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></dd></div>
                        <div class="col-span-2"><dt class="text-xs font-semibold text-sand-500 uppercase">Remarks</dt><dd class="text-sand-800">{{ $row['remarks'] ?? '—' }}</dd></div>
                    </dl>
                    <x-slot:footer>
                        <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Close</button>
                    </x-slot:footer>
                </x-dashboard.modal>
            @endforeach
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
