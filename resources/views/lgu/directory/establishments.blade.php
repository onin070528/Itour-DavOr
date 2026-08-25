@php
    $statusTone = fn ($status) => match ($status) {
        'Active' => 'success',
        'Pending Review' => 'warning',
        default => 'danger',
    };
    $categories = collect($listings)->pluck('category')->unique()->values();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Establishments"
        description="Tourism establishments operating in {{ $municipality }}. Each establishment manages its own profile — your office monitors and verifies."
    />

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 sm:flex-row sm:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search establishments..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="category" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c }}">{{ \App\Support\TourismCatalog::categoryLabel($c) }}</option>
                @endforeach
            </select>
            <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Pending Review">Pending Review</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>

        @if (count($listings))
            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[680px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Establishment</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($listings as $listing)
                            <tr
                                data-row
                                data-category="{{ $listing['category'] }}"
                                data-status="{{ $listing['status'] }}"
                                data-search-text="{{ strtolower($listing['name']) }}"
                                class="hover:bg-sand-50"
                            >
                                <td class="flex items-center gap-3 px-4 py-3">
                                    <span class="h-10 w-10 shrink-0 overflow-hidden rounded-sm bg-sand-200">
                                        <img src="{{ asset('storage/itour-images/'.$listing['image']) }}" alt="" class="h-full w-full object-cover">
                                    </span>
                                    <span class="font-medium text-sand-900">{{ $listing['name'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-sand-700">{{ \App\Support\TourismCatalog::categoryLabel($listing['category']) }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $listing['barangay'] }}</td>
                                <td class="px-4 py-3 text-sand-700">{{ $listing['contactPhone'] }}</td>
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
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Location</dt><dd class="text-sand-800">{{ $listing['barangay'] }}, {{ $municipality }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Contact Information</dt><dd class="text-sand-800">{{ $listing['contactOffice'] }} · {{ $listing['contactPhone'] }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Hours</dt><dd class="text-sand-800">{{ $listing['hours'] }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($listing['status'])">{{ $listing['status'] }}</x-dashboard.status-badge></dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Description</dt><dd class="text-sand-800">{{ $listing['description'] }}</dd></div>
                    </dl>

                    <x-slot:footer>
                        @if ($listing['status'] === 'Pending Review')
                            <button
                                type="button"
                                data-modal-close
                                data-toast-message="{{ $listing['name'] }} marked as verified."
                                class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                            >
                                <i class="ti ti-circle-check" aria-hidden="true"></i>
                                Verify Establishment
                            </button>
                        @else
                            <span class="text-xs text-sand-500">Establishment profile is managed by its owner.</span>
                        @endif
                    </x-slot:footer>
                </x-dashboard.modal>
            @endforeach
        @endif

        <x-dashboard.empty-state
            data-empty-state
            class="{{ count($listings) ? 'hidden' : '' }} mt-3"
            icon="ti-building-store"
            title="No establishments in {{ $municipality }} yet"
            description="Accredited establishments in your municipality will appear here once registered."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
