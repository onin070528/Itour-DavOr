{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Establishments directory — monitor and submit to PTO.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $statusTone = fn ($status) => match ($status) {
        'PUBLISHED' => 'success',
        'FOR_LGU_REVIEW' => 'info',
        'DRAFT', 'FOR_PTO_REVIEW', 'UNPUBLISHED' => 'warning',
        default => 'danger',
    };
    $statusLabel = fn ($status) => match ($status) {
        'DRAFT' => 'Draft',
        'FOR_LGU_REVIEW' => 'Waiting for Your Review',
        'FOR_PTO_REVIEW' => 'For PTO Review',
        'PUBLISHED' => 'Published',
        'UNPUBLISHED' => 'Unpublished',
        default => $status,
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
                <option value="DRAFT">Draft</option>
                <option value="FOR_LGU_REVIEW">Waiting for Your Review</option>
                <option value="FOR_PTO_REVIEW">For PTO Review</option>
                <option value="PUBLISHED">Published</option>
                <option value="UNPUBLISHED">Unpublished</option>
            </select>
        </div>

        @if (count($listings))
            <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($listings) }}</span> of {{ count($listings) }} establishments</p>

            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[680px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Establishment</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Photo Last Updated</th>
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
                                <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($listing['status'])">{{ $statusLabel($listing['status']) }}</x-dashboard.status-badge></td>
                                <td class="px-4 py-3 text-sand-700">{{ ($photoLastUpdated[$listing['id']] ?? null)?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('lgu.images.manage', $listing['id']) }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                        Photos
                                    </a>
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
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($listing['status'])">{{ $statusLabel($listing['status']) }}</x-dashboard.status-badge></dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Description</dt><dd class="text-sand-800">{{ $listing['description'] }}</dd></div>
                    </dl>

                    <x-slot:footer>
                        @if (in_array($listing['status'], ['DRAFT', 'UNPUBLISHED', 'FOR_LGU_REVIEW'], true))
                            <button type="button" data-modal-open="return-establishment-{{ $listing['id'] }}" class="rounded-sm border border-danger/30 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger-bg">
                                Return to Establishment
                            </button>
                            <form method="POST" action="{{ route('lgu.directory.establishments.submit', $listing['id']) }}">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="button"
                                    data-confirm-trigger
                                    data-confirm-title="Submit {{ $listing['name'] }} to PTO?"
                                    data-confirm-message="The Provincial Tourism Office will review it before it goes live."
                                    data-confirm-label="Approve and Submit to PTO"
                                    data-confirm-tone="success"
                                    class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                                >
                                    <i class="ti ti-send" aria-hidden="true"></i>
                                    Approve and Submit to PTO
                                </button>
                            </form>
                        @elseif ($listing['status'] === 'FOR_PTO_REVIEW')
                            <button type="button" data-modal-open="return-establishment-{{ $listing['id'] }}" class="rounded-sm border border-danger/30 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger-bg">
                                Return to Establishment
                            </button>
                            <span class="text-xs text-sand-500">Waiting for the Provincial Tourism Office's decision.</span>
                        @else
                            <span class="text-xs text-sand-500">Establishment profile is managed by its owner.</span>
                        @endif
                    </x-slot:footer>
                </x-dashboard.modal>

                @if (in_array($listing['status'], ['DRAFT', 'FOR_PTO_REVIEW', 'UNPUBLISHED', 'FOR_LGU_REVIEW'], true))
                    <x-dashboard.modal id="return-establishment-{{ $listing['id'] }}" title="Return to Establishment">
                        <form id="return-establishment-form-{{ $listing['id'] }}" method="POST" action="{{ route('lgu.directory.establishments.return', $listing['id']) }}" class="flex flex-col gap-3">
                            @csrf
                            @method('PATCH')
                            <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea name="reason" rows="3" required placeholder="What does the establishment need to fix?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                        </form>
                        <x-slot:footer>
                            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                            <button type="submit" form="return-establishment-form-{{ $listing['id'] }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return to Establishment</button>
                        </x-slot:footer>
                    </x-dashboard.modal>
                @endif
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
