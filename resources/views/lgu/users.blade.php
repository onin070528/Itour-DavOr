@php
    $statusTone = fn ($status) => $status === 'Active' ? 'success' : 'danger';
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Users"
        :description="'Establishment accounts registered in '.$municipality.'.'"
    >
        <x-slot:actions>
            <button type="button" data-modal-open="user-form-modal" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Add Establishment
            </button>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by establishment, owner, email, or barangay..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="category" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category['slug'] }}">{{ $category['label'] }}</option>
                @endforeach
            </select>
            <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
            <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                Reset
            </button>
        </div>

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $users->count() }}</span> of {{ $users->count() }} accounts</p>

        <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[820px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Establishment</th>
                        <th class="px-4 py-3">Barangay</th>
                        <th class="px-4 py-3">Owner / Manager</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($users as $u)
                        @php
                            $listing = $u->establishment;
                            $hours = \App\Support\BusinessHours::parse($listing?->hours);
                            $editValues = json_encode([
                                'name' => $listing?->name ?? $u->organization_name,
                                'category' => $listing?->category,
                                'barangay' => $listing?->barangay,
                                'ownerName' => $listing?->owner_name ?? $u->name,
                                'contactPhone' => $listing?->contact_phone,
                                'email' => $u->email,
                                'hoursDays' => $hours['days'] ?? '',
                                'hoursOpen' => $hours['opens'] ?? '',
                                'hoursClose' => $hours['closes'] ?? '',
                                'website' => $listing?->website,
                                'description' => $listing?->description,
                            ]);
                            $establishmentName = $listing?->name ?? $u->organization_name;
                            $ownerName = $listing?->owner_name ?? $u->name;
                        @endphp
                        <tr
                            data-row
                            data-status="{{ $u->status }}"
                            data-category="{{ $listing?->category }}"
                            data-search-text="{{ strtolower($establishmentName.' '.$ownerName.' '.$u->email.' '.$listing?->barangay) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-sand-900">{{ $establishmentName }}</p>
                                @if ($listing)
                                    <p class="text-xs text-sand-500">{{ \App\Support\TourismCatalog::categoryLabel($listing->category) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $listing?->barangay ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $ownerName }}</td>
                            <td class="px-4 py-3">
                                <p class="text-sand-700">{{ $u->email }}</p>
                                @if ($listing?->contact_phone)
                                    <p class="text-xs text-sand-500">{{ $listing->contact_phone }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($u->status)">{{ $u->status }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right">
                                <div class="relative inline-block">
                                    <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                        <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-44 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                        <button
                                            type="button"
                                            data-modal-open="user-form-modal"
                                            data-edit-trigger="user-form-modal"
                                            data-edit-values="{{ $editValues }}"
                                            data-edit-action="{{ route('lgu.users.update', $u->id) }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                        >
                                            <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route('lgu.users.toggleStatus', $u->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($u->status === 'Active')
                                                <button
                                                    type="button"
                                                    data-confirm-trigger
                                                    data-confirm-title="Disable {{ $u->name }}?"
                                                    data-confirm-message="They will immediately lose access to their iTOUR account."
                                                    data-confirm-label="Disable Account"
                                                    data-confirm-tone="danger"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-danger hover:bg-danger-bg"
                                                >
                                                    <i class="ti ti-toggle-left" aria-hidden="true"></i> Disable Account
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    data-confirm-trigger
                                                    data-confirm-title="Enable {{ $u->name }}?"
                                                    data-confirm-message="They will regain access to their iTOUR account."
                                                    data-confirm-label="Enable Account"
                                                    data-confirm-tone="success"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-success hover:bg-success-bg"
                                                >
                                                    <i class="ti ti-toggle-right" aria-hidden="true"></i> Enable Account
                                                </button>
                                            @endif
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-dashboard.empty-state
            data-empty-state
            class="hidden mt-3"
            icon="ti-users"
            title="No users match your filters"
            description="Try a different status."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>

    <x-dashboard.modal id="user-form-modal" title="Establishment Information" max-width="max-w-2xl">
        <form
            id="user-form"
            method="POST"
            action="{{ route('lgu.users.store') }}"
            data-default-action="{{ route('lgu.users.store') }}"
            data-default-method="POST"
            class="flex flex-col gap-5"
        >
            @csrf
            <fieldset class="flex flex-col gap-4">
                <legend class="mb-3 text-xs font-semibold tracking-wide text-sand-500 uppercase">Establishment Details</legend>
                <div>
                    <label for="establishment-name" class="mb-1 block text-xs font-semibold text-sand-700">Establishment Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="establishment-name" name="name" type="text" required maxlength="255" placeholder="e.g. Dahican Surf Resort" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="establishment-category" class="mb-1 block text-xs font-semibold text-sand-700">Category <span class="text-danger" aria-hidden="true">*</span></label>
                        <select id="establishment-category" name="category" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            <option value="">Select a category...</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['slug'] }}">{{ $category['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="establishment-barangay" class="mb-1 block text-xs font-semibold text-sand-700">Barangay <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-barangay" name="barangay" type="text" required maxlength="255" placeholder="e.g. Dahican" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-sand-500">Located in {{ $municipality }}.</p>
                    </div>
                </div>
                <div>
                    <span class="mb-1 block text-xs font-semibold text-sand-700">Business Hours</span>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <select name="hoursDays" aria-label="Open days" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            <option value="">Days...</option>
                            @foreach (\App\Support\BusinessHours::days() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="hoursOpen" aria-label="Opening time" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            <option value="">Opens at...</option>
                            <option value="{{ \App\Support\BusinessHours::OPEN_24_HOURS }}">Open 24 hours</option>
                            @foreach (\App\Support\BusinessHours::times() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="hoursClose" aria-label="Closing time" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            <option value="">Closes at...</option>
                            @foreach (\App\Support\BusinessHours::times() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="mt-1 text-xs text-sand-500">Leave the closing time blank for "Open 24 hours".</p>
                </div>
                <div>
                    <label for="establishment-description" class="mb-1 block text-xs font-semibold text-sand-700">Description</label>
                    <textarea id="establishment-description" name="description" rows="3" maxlength="2000" placeholder="Short description of the establishment and its services" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                </div>
            </fieldset>

            <fieldset class="flex flex-col gap-4 border-t border-sand-200 pt-4">
                <legend class="mb-3 text-xs font-semibold tracking-wide text-sand-500 uppercase">Contact &amp; Account</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="establishment-owner" class="mb-1 block text-xs font-semibold text-sand-700">Owner / Manager Name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-owner" name="ownerName" type="text" required maxlength="255" placeholder="e.g. Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="establishment-phone" class="mb-1 block text-xs font-semibold text-sand-700">Contact Number <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-phone" name="contactPhone" type="tel" required maxlength="50" placeholder="e.g. 0917 123 4567" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="establishment-email" class="mb-1 block text-xs font-semibold text-sand-700">Email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-email" name="email" type="email" required maxlength="255" placeholder="e.g. frontdesk@example.com" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-sand-500">Used to sign in to the establishment's iTOUR account.</p>
                    </div>
                    <div>
                        <label for="establishment-website" class="mb-1 block text-xs font-semibold text-sand-700">Website / Facebook Page</label>
                        <input id="establishment-website" name="website" type="url" maxlength="255" placeholder="https://" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </fieldset>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="user-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Save
            </button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
