{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: PTO Tourism Directory — category-driven list and map.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $statusTone = fn ($status) => match ($status) {
        'Active', 'PUBLISHED' => 'success',
        'FOR_LGU_REVIEW' => 'info',
        'Pending Review', 'DRAFT', 'FOR_PTO_REVIEW', 'FOR_CORRECTION', 'UNPUBLISHED' => 'warning',
        'Suspended', 'Inactive' => 'danger',
        default => 'neutral',
    };
    $statusLabel = fn ($status) => match ($status) {
        'DRAFT' => 'Draft',
        'FOR_LGU_REVIEW' => 'Waiting for LGU Review',
        'FOR_PTO_REVIEW' => 'For PTO Review',
        'FOR_CORRECTION' => 'Returned to LGU',
        'PUBLISHED' => 'Published',
        'UNPUBLISHED' => 'Unpublished',
        default => $status,
    };

    $plottedListings = $listings
        ->filter(fn ($listing) => $listing->lst_lat !== null && $listing->lst_lng !== null && ! $listing->isTourGuide())
        ->map(fn ($listing) => [
            'name' => $listing->lst_name,
            'category' => $listing->categoryRecord?->cat_name,
            'municipality' => $listing->lst_municipality,
            'barangay' => $listing->lst_barangay,
            'status' => $listing->lst_status,
            'image' => $listing->lst_image ? asset('storage/itour-images/'.$listing->lst_image) : null,
            'lat' => $listing->lst_lat,
            'lng' => $listing->lst_lng,
        ])
        ->values();
    $unplottedCount = $listings->count() - $plottedListings->count();
    // Establishments and destination-only records alike go through the PTO review (Objective 3, D3).
    $intReviewCount = $listings->filter(fn ($listing) => $listing->isAwaitingPtoDecision())->count();
@endphp

{{-- Same key as the location picker's push, so Mapbox GL loads once per page. --}}
@pushOnce('head', 'mapbox-gl')
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.css">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.js"></script>
@endPushOnce

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourism Directory"
        description="Every destination, establishment, and Travel & Tours guide registered across the province."
    >
        <x-slot:actions>
            <button type="button" data-modal-open="listing-form-modal" data-modal-mode="add" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Add Listing
            </button>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($intReviewCount > 0)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-clipboard-list" aria-hidden="true"></i> {{ $intReviewCount }} destination {{ \Illuminate\Support\Str::plural('listing', $intReviewCount) }} waiting for your review.</p>
            <a href="{{ route('pto.destinationReviews.index') }}" class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900">Review listings</a>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-[240px_1fr]">
        {{-- Categories panel: data-driven from tblcategories — adding/renaming a
             category here needs no template or JS change. --}}
        <div data-category-panel="#category-filter" class="flex flex-col gap-1 rounded-md border border-sand-200 bg-sand-0 p-3">
            <p class="px-2 pb-1 text-xs font-bold tracking-widest text-sand-500 uppercase">Categories</p>
            <button type="button" data-category-value="" class="flex items-center justify-between rounded-sm px-2.5 py-2 text-left text-sm font-semibold text-primary-700 bg-primary-50">
                <span>All</span>
                <span class="text-xs text-sand-500">{{ $listings->count() }}</span>
            </button>
            @foreach ($categories as $category)
                <button type="button" data-category-value="{{ $category->cat_id }}" class="flex items-center justify-between rounded-sm px-2.5 py-2 text-left text-sm font-semibold text-sand-700 hover:bg-sand-50">
                    <span>{{ $category->cat_name }}</span>
                    <span class="text-xs text-sand-500">{{ $categoryCounts[$category->cat_id] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div>
            <div data-tabs class="flex items-center gap-1 border-b border-sand-200">
                <button type="button" data-tab-target="list" aria-selected="true" class="border-b-2 border-primary-700 px-3 py-2.5 text-sm font-semibold text-primary-700">
                    <i class="ti ti-list" aria-hidden="true"></i> List
                </button>
                <button type="button" data-tab-target="map" aria-selected="false" class="border-b-2 border-transparent px-3 py-2.5 text-sm font-semibold text-sand-500">
                    <i class="ti ti-map" aria-hidden="true"></i> Map
                </button>
            </div>

            <div data-tab-panel="list" data-filterable-table data-page-size="10" class="mt-4">
                <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
                    <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                        <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                        <input data-filter-input type="search" placeholder="Search the directory..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                    </div>
                    <select data-filter-select data-filter-key="municipality" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                        <option value="">All Municipalities</option>
                        @foreach ($municipalities as $m)
                            <option value="{{ $m->mun_name }}">{{ $m->mun_name }}</option>
                        @endforeach
                    </select>
                    <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                        <option value="">All Statuses</option>
                        <option value="Active">Active (destinations)</option>
                        <option value="DRAFT">Draft</option>
                        <option value="FOR_LGU_REVIEW">Waiting for LGU Review</option>
                        <option value="FOR_PTO_REVIEW">For PTO Review</option>
                        <option value="FOR_CORRECTION">Returned to LGU</option>
                        <option value="PUBLISHED">Published</option>
                        <option value="UNPUBLISHED">Unpublished</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Archived">Archived</option>
                    </select>
                    <select id="category-filter" data-filter-select data-filter-key="category" class="hidden">
                        <option value="">All</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->cat_id }}">{{ $category->cat_name }}</option>
                        @endforeach
                    </select>
                    <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                        Reset
                    </button>
                </div>

                <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $listings->count() }}</span> of {{ $listings->count() }} listings</p>

                <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                    <table class="w-full min-w-[860px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Owner / License No.</th>
                                <th class="px-4 py-3">Municipality</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">QR</th>
                                <th class="px-4 py-3">Photo Last Updated</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($listings as $listing)
                                @php
                                    $isGuide = $listing->isTourGuide();
                                    $isDestination = $listing->isDestinationOnly();
                                    $editValues = [
                                        'name' => $listing->lst_name,
                                        'cat_id' => $listing->cat_id,
                                        // A destination's lst_type is its destination type, kept off the establishment Type select.
                                        'type' => $isDestination ? '' : $listing->lst_type,
                                        'destination_type' => $isDestination ? $listing->lst_type : '',
                                        'managing_level' => $isDestination ? $listing->managingLevel()->value : '',
                                        'visitor_information' => $isDestination ? $listing->lst_visitor_information : '',
                                        'entrance_fee' => $isDestination ? $listing->lst_entrance_fee : '',
                                        'owner_name' => $listing->lst_owner_name,
                                        'municipality' => $listing->lst_municipality,
                                        'barangay' => $listing->lst_barangay,
                                        'lat' => $listing->lst_lat,
                                        'lng' => $listing->lst_lng,
                                        'description' => $listing->lst_description,
                                        'contact_office' => $listing->lst_contact_office,
                                        'contact_phone' => $listing->lst_contact_phone,
                                        'email' => $listing->lst_email,
                                        'website' => $listing->lst_website,
                                        'hours' => $listing->lst_hours,
                                        'license_number' => $listing->lst_license_number,
                                        'accreditation_status' => $listing->lst_accreditation_status,
                                        'category_note' => $listing->lst_category_note,
                                    ];
                                @endphp
                                <tr
                                    data-row
                                    data-municipality="{{ $listing->lst_municipality }}"
                                    data-category="{{ $listing->cat_id }}"
                                    data-status="{{ $listing->lst_status }}"
                                    data-search-text="{{ strtolower($listing->lst_name.' '.$listing->lst_municipality.' '.$listing->lst_owner_name) }}"
                                    class="hover:bg-sand-50"
                                >
                                    <td class="px-4 py-3 font-medium text-sand-900">{{ $listing->lst_name }}</td>
                                    <td class="px-4 py-3 text-sand-700">
                                        {{ $listing->categoryRecord?->cat_name }}
                                        @if ($listing->lst_type)
                                            <span class="ml-1 rounded-sm bg-sand-100 px-1.5 py-0.5 text-[11px] text-sand-600">{{ $listing->lst_type }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sand-700">{{ $isGuide ? $listing->lst_license_number : $listing->lst_owner_name }}</td>
                                    <td class="px-4 py-3 text-sand-700">{{ $listing->lst_municipality }}</td>
                                    <td class="px-4 py-3 text-sand-700">{{ $listing->lst_contact_phone }}</td>
                                    <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($listing->lst_status)">{{ $listing->lst_status }}</x-dashboard.status-badge></td>
                                    <td class="px-4 py-3">
                                        <x-dashboard.qr-cell :listing="$listing" />
                                    </td>
                                    <td class="px-4 py-3 text-sand-700">{{ $listing->publishedPhotoLastUpdatedAt()?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative inline-block">
                                            <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                                <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                            </button>
                                            <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-44 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                                <button type="button" data-modal-open="listing-view-{{ $listing->lst_id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                    <i class="ti ti-eye" aria-hidden="true"></i> View Details
                                                </button>
                                                <a href="{{ route('pto.images.manage', $listing) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                    <i class="ti ti-photo" aria-hidden="true"></i> Manage Photos
                                                </a>
                                                @unless ($listing->lst_category === 'destinations')
                                                    <a href="{{ route('listings.show', $listing) }}" target="_blank" rel="noopener" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                        <i class="ti ti-external-link" aria-hidden="true"></i> Preview as Public
                                                    </a>
                                                    @if ($listing->isAwaitingPtoDecision())
                                                        <a href="{{ route('pto.destinationReviews.show', $listing) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-primary-700 hover:bg-sand-50">
                                                            <i class="ti ti-clipboard-check" aria-hidden="true"></i> Review Listing
                                                        </a>
                                                    @endif
                                                    @if ($listing->lst_status === 'FOR_PTO_REVIEW')
                                                        <form method="POST" action="{{ route('pto.directory.publish', $listing) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button
                                                                type="button"
                                                                data-confirm-trigger
                                                                data-confirm-title="Publish {{ $listing->lst_name }}?"
                                                                data-confirm-message="This makes the listing publicly visible immediately."
                                                                data-confirm-label="Publish"
                                                                data-confirm-tone="success"
                                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                                            >
                                                                <i class="ti ti-circle-check" aria-hidden="true"></i> Publish
                                                            </button>
                                                        </form>
                                                        <button type="button" data-modal-open="return-to-lgu-{{ $listing->lst_id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-danger hover:bg-danger-bg">
                                                            <i class="ti ti-arrow-back-up" aria-hidden="true"></i> Return to LGU
                                                        </button>
                                                    @elseif ($listing->lst_status === 'PUBLISHED')
                                                        <button type="button" data-modal-open="unpublish-{{ $listing->lst_id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-danger hover:bg-danger-bg">
                                                            <i class="ti ti-eye-off" aria-hidden="true"></i> Unpublish
                                                        </button>
                                                    @endif
                                                @else
                                                    {{-- Destination-only records (Objective 3, D3/D10): reviewed on the review screen; a PTO-managed Draft is moved into review by the PTO. --}}
                                                    @if ($listing->isAwaitingPtoDecision())
                                                        <a href="{{ route('pto.destinationReviews.show', $listing) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-primary-700 hover:bg-sand-50">
                                                            <i class="ti ti-clipboard-check" aria-hidden="true"></i> Review Listing
                                                        </a>
                                                    @endif
                                                    @if ($listing->isManagedByPto() && in_array($listing->lst_status, ['DRAFT', \App\Models\Listing::STATUS_FOR_CORRECTION], true))
                                                        <form method="POST" action="{{ route('pto.directory.submit', $listing) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button
                                                                type="button"
                                                                data-confirm-trigger
                                                                data-confirm-title="Submit {{ $listing->lst_name }} for review?"
                                                                data-confirm-message="It moves to Pending Review. It is not public until you Approve &amp; Publish it on the review screen."
                                                                data-confirm-label="Submit for review"
                                                                data-confirm-tone="success"
                                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                                            >
                                                                <i class="ti ti-send" aria-hidden="true"></i> Submit for Review
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endunless
                                                <button
                                                    type="button"
                                                    data-modal-open="listing-form-modal"
                                                    data-edit-trigger="listing-form-modal"
                                                    data-edit-values="{{ json_encode($editValues) }}"
                                                    data-edit-action="{{ route('pto.directory.update', $listing) }}"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                                >
                                                    <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    data-modal-open="status-form-modal"
                                                    data-edit-trigger="status-form-modal"
                                                    data-edit-values="{{ json_encode(['status' => $listing->lst_status]) }}"
                                                    data-edit-action="{{ route('pto.directory.updateStatus', $listing) }}"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                                >
                                                    <i class="ti ti-toggle-right" aria-hidden="true"></i> Change Status
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach ($listings as $listing)
                    <x-dashboard.modal id="listing-view-{{ $listing->lst_id }}" :title="$listing->lst_name">
                        <dl class="flex flex-col gap-3 text-sm">
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Category</dt><dd class="text-sand-800">{{ $listing->categoryRecord?->cat_name }} @if ($listing->lst_type) · {{ $listing->lst_type }} @endif</dd></div>
                            @if ($listing->lst_category_note)
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Category Note</dt><dd class="text-sand-800">{{ $listing->lst_category_note }}</dd></div>
                            @endif
                            @if ($listing->isTourGuide())
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">License No.</dt><dd class="text-sand-800">{{ $listing->lst_license_number }}</dd></div>
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Accreditation Status</dt><dd class="text-sand-800">{{ $listing->lst_accreditation_status }}</dd></div>
                            @else
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Owner</dt><dd class="text-sand-800">{{ $listing->lst_owner_name }}</dd></div>
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Location</dt><dd class="text-sand-800">{{ $listing->lst_barangay }}, {{ $listing->lst_municipality }}</dd></div>
                                @if ($listing->isDestinationOnly() && $listing->lst_accreditation_status)
                                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Accreditation Status</dt><dd class="text-sand-800">{{ $listing->lst_accreditation_status }}</dd></div>
                                @endif
                            @endif
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Contact</dt><dd class="text-sand-800">{{ $listing->lst_contact_office }} · {{ $listing->lst_contact_phone }}</dd></div>
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($listing->lst_status)">{{ $statusLabel($listing->lst_status) }}</x-dashboard.status-badge></dd></div>
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Photo Last Updated</dt><dd class="text-sand-800">{{ $listing->publishedPhotoLastUpdatedAt()?->format('M j, Y') ?? '—' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Description</dt><dd class="text-sand-800">{{ $listing->lst_description }}</dd></div>
                        </dl>
                    </x-dashboard.modal>

                    @if ($listing->lst_category !== 'destinations' && $listing->lst_status === 'FOR_PTO_REVIEW')
                        <x-dashboard.modal id="return-to-lgu-{{ $listing->lst_id }}" title="Return to LGU">
                            <form id="return-to-lgu-form-{{ $listing->lst_id }}" method="POST" action="{{ route('pto.directory.returnToLgu', $listing) }}" class="flex flex-col gap-3">
                                @csrf
                                @method('PATCH')
                                <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="What does the LGU need to fix?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                            </form>
                            <x-slot:footer>
                                <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                                <button type="submit" form="return-to-lgu-form-{{ $listing->lst_id }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return to LGU</button>
                            </x-slot:footer>
                        </x-dashboard.modal>
                    @endif

                    @if ($listing->lst_category !== 'destinations' && $listing->lst_status === 'PUBLISHED')
                        <x-dashboard.modal id="unpublish-{{ $listing->lst_id }}" title="Unpublish Listing">
                            <form id="unpublish-form-{{ $listing->lst_id }}" method="POST" action="{{ route('pto.directory.unpublish', $listing) }}" class="flex flex-col gap-3">
                                @csrf
                                @method('PATCH')
                                <p class="text-sm text-sand-700">This takes the listing off the public site immediately. It stays unpublished until resubmitted and published again.</p>
                                <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="Why is this being unpublished?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                            </form>
                            <x-slot:footer>
                                <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                                <button type="submit" form="unpublish-form-{{ $listing->lst_id }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Unpublish</button>
                            </x-slot:footer>
                        </x-dashboard.modal>
                    @endif

                    {{-- PTO is read-only for QR codes: view, print, download — no on/off switch. --}}
                    <x-dashboard.qr-modal :listing="$listing" />
                @endforeach

                <x-dashboard.empty-state
                    data-empty-state
                    class="hidden mt-3"
                    icon="ti-list-details"
                    title="No listings match your filters"
                    description="Try a different category, municipality, or status."
                />

                <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
            </div>

            <div data-tab-panel="map" class="hidden mt-4">
                <div class="relative h-[560px] overflow-hidden rounded-md border border-sand-200 bg-sand-100">
                    <div class="absolute inset-0">
                        <div
                            id="tourism-map"
                            class="h-full w-full"
                            data-mapbox-token="{{ \App\Support\MapboxToken::browserToken() }}"
                            data-mapbox-center-lat="7.0"
                            data-mapbox-center-lng="126.3"
                        ></div>
                    </div>

                    <div class="absolute top-3 left-3 z-10 flex flex-wrap gap-2">
                        <div class="inline-flex overflow-hidden rounded-sm border border-sand-300 bg-sand-0 text-xs font-semibold shadow-sm" role="group" aria-label="Map view">
                            <button type="button" data-tourism-map-style="satellite" aria-pressed="true" class="cursor-pointer px-3 py-2 text-sand-700 transition-colors aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                                <i class="ti ti-satellite" aria-hidden="true"></i> Satellite
                            </button>
                            <button type="button" data-tourism-map-style="streets" aria-pressed="false" class="cursor-pointer border-l border-sand-300 px-3 py-2 text-sand-700 transition-colors aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                                <i class="ti ti-map" aria-hidden="true"></i> Streets
                            </button>
                        </div>
                        <button type="button" id="tourism-map-3d" aria-pressed="false" class="cursor-pointer rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-xs font-semibold text-sand-700 shadow-sm transition-colors aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                            <i class="ti ti-mountain" aria-hidden="true"></i> 3D terrain
                        </button>
                    </div>

                    <p id="tourism-map-status" class="absolute inset-x-0 bottom-0 z-10 bg-sand-0/90 px-4 py-2 text-xs text-sand-600" hidden></p>

                    <script type="application/json" id="tourism-map-data">@json($plottedListings)</script>
                </div>
                @if ($unplottedCount > 0)
                    <p class="mt-2 text-xs text-sand-500">
                        <i class="ti ti-info-circle text-primary-700" aria-hidden="true"></i>
                        {{ $unplottedCount }} {{ Str::plural('listing', $unplottedCount) }} without coordinates — including every Tour Guide, which is list-only — {{ $unplottedCount === 1 ? 'is' : 'are' }} not shown on the map.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Shared Add / Edit modal --}}
    <x-dashboard.modal id="listing-form-modal" title="Directory Listing" max-width="max-w-2xl">
        <form
            id="listing-form"
            method="POST"
            action="{{ route('pto.directory.store') }}"
            data-default-action="{{ route('pto.directory.store') }}"
            data-default-method="POST"
            class="flex flex-col gap-4"
        >
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Name <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="name" type="text" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Category <span class="text-danger" aria-hidden="true">*</span></label>
                    <select name="cat_id" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        @foreach ($categories as $category)
                            <option value="{{ $category->cat_id }}" data-category-name="{{ $category->cat_name }}">{{ $category->cat_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-show-when="establishment" class="hidden">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Type <span class="text-danger" aria-hidden="true">*</span></label>
                    <x-dashboard.establishment-type-select :categories="$categories" />
                </div>
            </div>

            {{-- Destination-only fields (Objective 3): shown only for Tourist Destinations and ignored by the server otherwise. --}}
            <div data-show-when="destination" class="hidden flex flex-col gap-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Destination Type</label>
                        <select name="destination_type" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            <option value="">Not set</option>
                            @foreach (config('tourism_directory.destination_types') as $strDestinationType)
                                <option value="{{ $strDestinationType }}">{{ $strDestinationType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Managed By</label>
                        <select name="managing_level" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            @foreach (\App\Enums\ManagingLevel::cases() as $objManagingLevel)
                                <option value="{{ $objManagingLevel->value }}">{{ $objManagingLevel->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-xs text-sand-500">The LGU of the municipality edits and submits an LGU-managed destination; a PTO-managed one is edited and submitted by the PTO. The Contact Office below is shown as the destination's managing office.</p>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Visitor Information</label>
                    <textarea name="visitor_information" rows="3" maxlength="5000" placeholder="Permits, what to bring, safety reminders" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Entrance Fee</label>
                    <input name="entrance_fee" type="text" maxlength="255" placeholder="e.g. PHP 50 adults, PHP 20 children" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div data-show-when="others" class="hidden">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Category Note <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="category_note" type="text" placeholder="Describe this category" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <div data-show-when="guide-or-destination" class="hidden grid grid-cols-2 gap-3">
                <div data-show-when="guide" class="hidden">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">License No. <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="license_number" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                {{-- The one existing accreditation field (lst_accreditation_status), for a tour guide or a destination. --}}
                <div data-show-when="guide-or-destination" class="hidden">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Accreditation Status</label>
                    <input name="accreditation_status" type="text" maxlength="255" placeholder="e.g. DOT Accredited" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div data-hide-when="guide" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Owner / Manager</label>
                    <input name="owner_name" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Barangay <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="barangay" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Municipality <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="municipality" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    @foreach ($municipalities as $m)
                        <option value="{{ $m->mun_name }}">{{ $m->mun_name }}</option>
                    @endforeach
                </select>
            </div>

            <div data-hide-when="guide" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Latitude</label>
                    <input id="listing-form-lat" name="lat" type="text" inputmode="decimal" placeholder="e.g. 6.9578" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Longitude</label>
                    <input id="listing-form-lng" name="lng" type="text" inputmode="decimal" placeholder="e.g. 126.2478" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

            <x-dashboard.location-picker data-hide-when="guide" latitude-input="listing-form-lat" longitude-input="listing-form-lng" />

            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Description</label>
                <textarea name="description" rows="3" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Office</label>
                    <input name="contact_office" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Phone</label>
                    <input name="contact_phone" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div data-hide-when="guide" class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Email</label>
                    <input name="email" type="email" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Website</label>
                    <input name="website" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Hours</label>
                    <input name="hours" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="listing-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Save Listing</button>
        </x-slot:footer>
    </x-dashboard.modal>

    {{-- Shared Change Status modal: always requires a reason, which OperationLogger writes to the audit trail.
         Which changes are allowed depends on the record's current status (Listing::statusChangeOptions()) and
         is enforced on the server; nothing here publishes a record (Objective 3, D3). --}}
    <x-dashboard.modal id="status-form-modal" title="Change Status">
        <form id="status-form" method="POST" class="flex flex-col gap-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Status <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="status" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="Suspended">Suspended</option>
                    <option value="Archived">Archived</option>
                    <option value="DRAFT">Return to Draft (restore archived / reinstate suspended destination)</option>
                </select>
                <p class="mt-1 text-xs text-sand-500">A destination returned to Draft must be submitted and approved again before it is public. Archived destinations can only return to Draft; establishments can be suspended or archived.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea name="reason" rows="3" required placeholder="Why is this status changing?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="status-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Save Status</button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
