<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Destinations"
        description="Manage tourism destinations in {{ $municipality }}."
    >
        <x-slot:actions>
            <button type="button" data-modal-open="destination-form-modal" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Add Destination
            </button>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table class="mt-6">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <div class="flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 sm:max-w-sm">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search destinations..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
        </div>

        @if (count($destinations))
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($destinations as $d)
                    @php
                        $editValues = json_encode([
                            'name' => $d['name'],
                            'barangay' => $d['barangay'],
                            'description' => $d['description'],
                            'contactOffice' => $d['contactOffice'],
                            'contactPhone' => $d['contactPhone'],
                        ]);
                    @endphp
                    <div data-row data-search-text="{{ strtolower($d['name']) }}" class="overflow-hidden rounded-md border border-sand-200 bg-sand-0">
                        <div class="relative h-32 bg-sand-200">
                            <img src="{{ asset('storage/itour-images/'.$d['image']) }}" alt="{{ $d['name'] }}" loading="lazy" class="h-full w-full object-cover">
                            <span class="absolute top-2 left-2 rounded-sm bg-sand-900/50 px-2 py-0.5 text-[11px] font-semibold text-sand-0">{{ $d['barangay'] }}</span>
                        </div>
                        <div class="p-3.5">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-display text-sm font-bold text-sand-900">{{ $d['name'] }}</p>
                                <div class="relative shrink-0">
                                    <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                        <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-40 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                        <button type="button" data-modal-open="destination-view-{{ $d['id'] }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                            <i class="ti ti-eye" aria-hidden="true"></i> View Details
                                        </button>
                                        <button
                                            type="button"
                                            data-modal-open="destination-form-modal"
                                            data-edit-trigger="destination-form-modal"
                                            data-edit-values="{{ $editValues }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                        >
                                            <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                                        </button>
                                        <button
                                            type="button"
                                            data-confirm-trigger
                                            data-confirm-title="Archive {{ $d['name'] }}?"
                                            data-confirm-message="Archived destinations are hidden from the public site until restored."
                                            data-confirm-label="Archive"
                                            data-confirm-tone="danger"
                                            data-confirm-success="{{ $d['name'] }} was archived."
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-danger hover:bg-danger-bg"
                                        >
                                            <i class="ti ti-archive" aria-hidden="true"></i> Archive
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-1 flex items-center gap-1 text-xs text-sand-500"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ $d['barangay'] }}, {{ $municipality }}</p>
                            <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-sand-700"><i class="ti ti-star text-accent-500" aria-hidden="true"></i>{{ number_format($d['rating'], 1) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach ($destinations as $d)
                <x-dashboard.modal id="destination-view-{{ $d['id'] }}" :title="$d['name']">
                    <img src="{{ asset('storage/itour-images/'.$d['image']) }}" alt="{{ $d['name'] }}" class="mb-4 h-40 w-full rounded-md object-cover">
                    <dl class="flex flex-col gap-3 text-sm">
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Municipality</dt><dd class="text-sand-800">{{ $municipality }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Location</dt><dd class="text-sand-800">{{ $d['barangay'] }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Description</dt><dd class="text-sand-800">{{ $d['description'] }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Contact / Information</dt><dd class="text-sand-800">{{ $d['contactOffice'] }} · {{ $d['contactPhone'] }}</dd></div>
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Tags</dt><dd class="flex flex-wrap gap-1.5">
                            @foreach ($d['tags'] as $tag)<span class="rounded-sm bg-sand-100 px-2 py-0.5 text-xs text-sand-700">{{ $tag }}</span>@endforeach
                        </dd></div>
                    </dl>
                </x-dashboard.modal>
            @endforeach
        @endif

        <x-dashboard.empty-state
            data-empty-state
            class="{{ count($destinations) ? 'hidden' : '' }} mt-4"
            icon="ti-map-search"
            title="No destinations in {{ $municipality }} yet"
            description="Use Add Destination to register your municipality's first tourism destination."
        >
            <x-slot:action>
                <button type="button" data-modal-open="destination-form-modal" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    Add Destination
                </button>
            </x-slot:action>
        </x-dashboard.empty-state>
    </div>

    {{-- Shared Add / Edit modal. Municipality is fixed to this account's assignment. --}}
    <x-dashboard.modal id="destination-form-modal" title="Destination">
        <form class="flex flex-col gap-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Destination Name</label>
                <input name="name" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Municipality</label>
                    <input type="text" value="{{ $municipality }}" disabled class="w-full rounded-sm border border-sand-200 bg-sand-100 px-3 py-2 text-sm text-sand-500">
                    <p class="mt-1 text-[11px] text-sand-500">Set automatically from your account.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Barangay / Location</label>
                    <input name="barangay" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Description</label>
                <textarea name="description" rows="3" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Office</label>
                    <input name="contactOffice" type="text" value="{{ $user->organization_name }}" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Phone</label>
                    <input name="contactPhone" type="text" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Images</label>
                <div class="flex items-center justify-center rounded-md border border-dashed border-sand-300 px-4 py-6 text-center">
                    <div>
                        <i class="ti ti-photo-plus text-2xl text-sand-400" aria-hidden="true"></i>
                        <p class="mt-1 text-xs text-sand-500">Drag and drop images, or click to browse</p>
                    </div>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-700 hover:border-sand-400">Cancel</button>
            <button type="button" data-modal-close data-toast-message="Destination saved." class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Save Destination
            </button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
