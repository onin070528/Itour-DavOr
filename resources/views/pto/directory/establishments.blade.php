@php
    $statusTone = fn ($status) => match ($status) {
        'Active' => 'success',
        'Pending Review' => 'warning',
        default => 'danger',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Establishments"
        description="Accredited tourism establishments registered across the province."
    />

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search establishments..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="municipality" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Municipalities</option>
                @foreach ($municipalities as $m)
                    <option value="{{ $m['name'] }}">{{ $m['name'] }}</option>
                @endforeach
            </select>
            <select data-filter-select data-filter-key="category" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Categories</option>
                @foreach ($categories as $c)
                    @if ($c['slug'] !== 'destinations')
                        <option value="{{ $c['slug'] }}">{{ $c['label'] }}</option>
                    @endif
                @endforeach
            </select>
            <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Pending Review">Pending Review</option>
                <option value="Inactive">Inactive</option>
            </select>
            <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                Reset
            </button>
        </div>

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($listings) }}</span> of {{ count($listings) }} establishments</p>

        <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[760px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Establishment</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Municipality</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($listings as $listing)
                        <tr
                            data-row
                            data-municipality="{{ $listing['municipality'] }}"
                            data-category="{{ $listing['category'] }}"
                            data-status="{{ $listing['status'] }}"
                            data-search-text="{{ strtolower($listing['name'].' '.$listing['municipality']) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="flex items-center gap-3 px-4 py-3">
                                <span class="h-10 w-10 shrink-0 overflow-hidden rounded-sm bg-sand-200">
                                    <img src="{{ asset('storage/itour-images/'.$listing['image']) }}" alt="" class="h-full w-full object-cover">
                                </span>
                                <span class="font-medium text-sand-900">{{ $listing['name'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ \App\Support\TourismCatalog::categoryLabel($listing['category']) }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $listing['municipality'] }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $listing['contactOffice'] }}<br><span class="text-xs text-sand-500">{{ $listing['contactPhone'] }}</span></td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($listing['status'])">{{ $listing['status'] }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" data-modal-open="establishment-view-{{ $listing['id'] }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                    View
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @foreach ($listings as $listing)
            <x-dashboard.modal id="establishment-view-{{ $listing['id'] }}" :title="$listing['name']">
                <img src="{{ asset('storage/itour-images/'.$listing['image']) }}" alt="{{ $listing['name'] }}" class="mb-4 h-40 w-full rounded-md object-cover">
                <dl class="flex flex-col gap-3 text-sm">
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Category</dt><dd class="text-sand-800">{{ \App\Support\TourismCatalog::categoryLabel($listing['category']) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Location</dt><dd class="text-sand-800">{{ $listing['barangay'] }}, {{ $listing['municipality'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Contact Information</dt><dd class="text-sand-800">{{ $listing['contactOffice'] }} · {{ $listing['contactPhone'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Hours</dt><dd class="text-sand-800">{{ $listing['hours'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($listing['status'])">{{ $listing['status'] }}</x-dashboard.status-badge></dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Description</dt><dd class="text-sand-800">{{ $listing['description'] }}</dd></div>
                </dl>
            </x-dashboard.modal>
        @endforeach

        <x-dashboard.empty-state
            data-empty-state
            class="hidden mt-3"
            icon="ti-building-store"
            title="No establishments match your filters"
            description="Try a different municipality, category, or status."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
