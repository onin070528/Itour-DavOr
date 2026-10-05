@php
    $statusTone = fn ($status) => match ($status) {
        'Active', 'PUBLISHED' => 'success',
        'FOR_LGU_REVIEW' => 'info',
        'Pending Review', 'DRAFT', 'FOR_PTO_REVIEW', 'UNPUBLISHED' => 'warning',
        'Suspended', 'Inactive' => 'danger',
        default => 'neutral',
    };
    $statusLabel = fn ($status) => match ($status) {
        'DRAFT' => 'Draft',
        'FOR_LGU_REVIEW' => 'Waiting for LGU Review',
        'FOR_PTO_REVIEW' => 'For PTO Review',
        'PUBLISHED' => 'Published',
        'UNPUBLISHED' => 'Unpublished',
        default => $status,
    };

    $plottedListings = $listings
        ->filter(fn ($listing) => $listing->lat !== null && $listing->lng !== null && ! $listing->isTourGuide())
        ->map(fn ($listing) => [
            'name' => $listing->name,
            'category' => $listing->categoryRecord?->cat_name,
            'municipality' => $listing->municipality,
            'barangay' => $listing->barangay,
            'status' => $listing->status,
            'image' => $listing->image ? asset('storage/itour-images/'.$listing->image) : null,
            'lat' => $listing->lat,
            'lng' => $listing->lng,
        ])
        ->values();
    $unplottedCount = $listings->count() - $plottedListings->count();
@endphp

@push('head')
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.css">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.js"></script>
@endpush

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
                            <option value="{{ $m->name }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                    <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                        <option value="">All Statuses</option>
                        <option value="Active">Active (destinations)</option>
                        <option value="DRAFT">Draft</option>
                        <option value="FOR_LGU_REVIEW">Waiting for LGU Review</option>
                        <option value="FOR_PTO_REVIEW">For PTO Review</option>
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
                                    $isQrEnabled = $listing->isQrEnabled();
                                    $editValues = [
                                        'name' => $listing->name,
                                        'cat_id' => $listing->cat_id,
                                        'type' => $listing->type,
                                        'owner_name' => $listing->owner_name,
                                        'municipality' => $listing->municipality,
                                        'barangay' => $listing->barangay,
                                        'lat' => $listing->lat,
                                        'lng' => $listing->lng,
                                        'description' => $listing->description,
                                        'contact_office' => $listing->contact_office,
                                        'contact_phone' => $listing->contact_phone,
                                        'email' => $listing->email,
                                        'website' => $listing->website,
                                        'hours' => $listing->hours,
                                        'license_number' => $listing->license_number,
                                        'accreditation_status' => $listing->accreditation_status,
                                        'category_note' => $listing->category_note,
                                    ];
                                @endphp
                                <tr
                                    data-row
                                    data-municipality="{{ $listing->municipality }}"
                                    data-category="{{ $listing->cat_id }}"
                                    data-status="{{ $listing->status }}"
                                    data-search-text="{{ strtolower($listing->name.' '.$listing->municipality.' '.$listing->owner_name) }}"
                                    class="hover:bg-sand-50"
                                >
                                    <td class="px-4 py-3 font-medium text-sand-900">{{ $listing->name }}</td>
                                    <td class="px-4 py-3 text-sand-700">
                                        {{ $listing->categoryRecord?->cat_name }}
                                        @if ($listing->type)
                                            <span class="ml-1 rounded-sm bg-sand-100 px-1.5 py-0.5 text-[11px] text-sand-600">{{ $listing->type }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sand-700">{{ $isGuide ? $listing->license_number : $listing->owner_name }}</td>
                                    <td class="px-4 py-3 text-sand-700">{{ $listing->municipality }}</td>
                                    <td class="px-4 py-3 text-sand-700">{{ $listing->contact_phone }}</td>
                                    <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($listing->status)">{{ $listing->status }}</x-dashboard.status-badge></td>
                                    <td class="px-4 py-3">
                                        @if ($isQrEnabled)
                                            <button type="button" data-modal-open="qr-view-{{ $listing->id }}" class="rounded-sm border border-sand-300 px-2.5 py-1 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                                <i class="ti ti-qrcode" aria-hidden="true"></i> View
                                            </button>
                                        @else
                                            <span class="text-xs text-sand-400">No QR</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sand-700">{{ $listing->publishedPhotoLastUpdatedAt()?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative inline-block">
                                            <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                                <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                            </button>
                                            <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-44 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                                <button type="button" data-modal-open="listing-view-{{ $listing->id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                    <i class="ti ti-eye" aria-hidden="true"></i> View Details
                                                </button>
                                                <a href="{{ route('pto.images.manage', $listing) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                    <i class="ti ti-photo" aria-hidden="true"></i> Manage Photos
                                                </a>
                                                @unless ($listing->category === 'destinations')
                                                    <a href="{{ route('listings.show', $listing) }}" target="_blank" rel="noopener" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                        <i class="ti ti-external-link" aria-hidden="true"></i> Preview as Public
                                                    </a>
                                                    @if ($listing->status === 'FOR_PTO_REVIEW')
                                                        <form method="POST" action="{{ route('pto.directory.publish', $listing) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button
                                                                type="button"
                                                                data-confirm-trigger
                                                                data-confirm-title="Publish {{ $listing->name }}?"
                                                                data-confirm-message="This makes the listing publicly visible immediately."
                                                                data-confirm-label="Publish"
                                                                data-confirm-tone="success"
                                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                                            >
                                                                <i class="ti ti-circle-check" aria-hidden="true"></i> Publish
                                                            </button>
                                                        </form>
                                                        <button type="button" data-modal-open="return-to-lgu-{{ $listing->id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-danger hover:bg-danger-bg">
                                                            <i class="ti ti-arrow-back-up" aria-hidden="true"></i> Return to LGU
                                                        </button>
                                                    @elseif ($listing->status === 'PUBLISHED')
                                                        <button type="button" data-modal-open="unpublish-{{ $listing->id }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-danger hover:bg-danger-bg">
                                                            <i class="ti ti-eye-off" aria-hidden="true"></i> Unpublish
                                                        </button>
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
                                                    data-edit-values="{{ json_encode(['status' => $listing->status]) }}"
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
                    <x-dashboard.modal id="listing-view-{{ $listing->id }}" :title="$listing->name">
                        <dl class="flex flex-col gap-3 text-sm">
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Category</dt><dd class="text-sand-800">{{ $listing->categoryRecord?->cat_name }} @if ($listing->type) · {{ $listing->type }} @endif</dd></div>
                            @if ($listing->category_note)
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Category Note</dt><dd class="text-sand-800">{{ $listing->category_note }}</dd></div>
                            @endif
                            @if ($listing->isTourGuide())
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">License No.</dt><dd class="text-sand-800">{{ $listing->license_number }}</dd></div>
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Accreditation Status</dt><dd class="text-sand-800">{{ $listing->accreditation_status }}</dd></div>
                            @else
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Owner</dt><dd class="text-sand-800">{{ $listing->owner_name }}</dd></div>
                                <div><dt class="text-xs font-semibold text-sand-500 uppercase">Location</dt><dd class="text-sand-800">{{ $listing->barangay }}, {{ $listing->municipality }}</dd></div>
                            @endif
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Contact</dt><dd class="text-sand-800">{{ $listing->contact_office }} · {{ $listing->contact_phone }}</dd></div>
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($listing->status)">{{ $statusLabel($listing->status) }}</x-dashboard.status-badge></dd></div>
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Photo Last Updated</dt><dd class="text-sand-800">{{ $listing->publishedPhotoLastUpdatedAt()?->format('M j, Y') ?? '—' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-sand-500 uppercase">Description</dt><dd class="text-sand-800">{{ $listing->description }}</dd></div>
                        </dl>
                    </x-dashboard.modal>

                    @if ($listing->category !== 'destinations' && $listing->status === 'FOR_PTO_REVIEW')
                        <x-dashboard.modal id="return-to-lgu-{{ $listing->id }}" title="Return to LGU">
                            <form id="return-to-lgu-form-{{ $listing->id }}" method="POST" action="{{ route('pto.directory.returnToLgu', $listing) }}" class="flex flex-col gap-3">
                                @csrf
                                @method('PATCH')
                                <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="What does the LGU need to fix?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                            </form>
                            <x-slot:footer>
                                <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                                <button type="submit" form="return-to-lgu-form-{{ $listing->id }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return to LGU</button>
                            </x-slot:footer>
                        </x-dashboard.modal>
                    @endif

                    @if ($listing->category !== 'destinations' && $listing->status === 'PUBLISHED')
                        <x-dashboard.modal id="unpublish-{{ $listing->id }}" title="Unpublish Listing">
                            <form id="unpublish-form-{{ $listing->id }}" method="POST" action="{{ route('pto.directory.unpublish', $listing) }}" class="flex flex-col gap-3">
                                @csrf
                                @method('PATCH')
                                <p class="text-sm text-sand-700">This takes the listing off the public site immediately. It stays unpublished until resubmitted and published again.</p>
                                <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="Why is this being unpublished?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                            </form>
                            <x-slot:footer>
                                <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                                <button type="submit" form="unpublish-form-{{ $listing->id }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Unpublish</button>
                            </x-slot:footer>
                        </x-dashboard.modal>
                    @endif

                    @if ($listing->isQrEnabled())
                        <x-dashboard.modal id="qr-view-{{ $listing->id }}" title="{{ $listing->name }} QR Code">
                            <div data-qr-mount class="mx-auto flex h-56 w-56 items-center justify-center rounded-md border border-sand-200 bg-sand-0 p-3 [&>svg]:h-full [&>svg]:w-full">
                                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(1)->generate(route('lgu.establishmentQr', ['establishment' => $listing->uuid])) !!}
                            </div>
                            <p class="mt-3 text-center text-xs text-sand-500">Tourists scan this to register their arrival at {{ $listing->name }}.</p>

                            <x-slot:footer>
                                <button type="button" data-qr-print class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                                    <i class="ti ti-printer" aria-hidden="true"></i> Print
                                </button>
                                <button type="button" data-qr-download data-qr-filename="{{ $listing->slug }}-qr.svg" class="rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                                    <i class="ti ti-download" aria-hidden="true"></i> Download
                                </button>
                            </x-slot:footer>
                        </x-dashboard.modal>
                    @endif
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
                            data-mapbox-token="{{ config('services.mapbox.token') }}"
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
                <div data-show-when="travel" class="hidden">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Type</label>
                    <select name="type" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option value="Tour Operator">Tour Operator</option>
                        <option value="Tour Guide">Tour Guide</option>
                    </select>
                </div>
            </div>

            <div data-show-when="others" class="hidden">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Category Note <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="category_note" type="text" placeholder="Describe this category" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <div data-show-when="guide" class="hidden grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">License No. <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="license_number" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Accreditation Status</label>
                    <input name="accreditation_status" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
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
                        <option value="{{ $m->name }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>

            <div data-hide-when="guide" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Latitude</label>
                    <input name="lat" type="text" inputmode="decimal" placeholder="e.g. 6.9578" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Longitude</label>
                    <input name="lng" type="text" inputmode="decimal" placeholder="e.g. 126.2478" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

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

    {{-- Shared Change Status modal: activate/suspend always requires a reason, which OperationLogger writes to the audit trail. --}}
    <x-dashboard.modal id="status-form-modal" title="Change Status">
        <form id="status-form" method="POST" class="flex flex-col gap-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Status <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="status" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="Active">Active</option>
                    <option value="Suspended">Suspended</option>
                    <option value="Archived">Archived</option>
                </select>
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
