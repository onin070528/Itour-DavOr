{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Accounts page — register establishments (with their login accounts) and destinations, and manage the accounts.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $statusTone = fn ($status) => $status === 'Active' ? 'success' : 'danger';
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Accounts"
        :description="'Establishment accounts and destinations registered in '.$municipality.'.'"
    >
        <x-slot:actions>
            <button type="button" data-modal-open="user-form-modal" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Add New
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
                            $hours = \App\Support\BusinessHours::parse($listing?->lst_hours);
                            $editValues = json_encode([
                                'name' => $listing?->lst_name ?? $u->usr_organization_name,
                                'category' => $listing?->lst_category,
                                'barangay' => $listing?->lst_barangay,
                                'ownerName' => $listing?->lst_owner_name ?? $u->usr_name,
                                'contactPhone' => $listing?->lst_contact_phone,
                                'email' => $u->usr_email,
                                'hoursDays' => $hours['days'] ?? '',
                                'hoursOpen' => $hours['opens'] ?? '',
                                'hoursClose' => $hours['closes'] ?? '',
                                'website' => $listing?->lst_website,
                                'description' => $listing?->lst_description,
                            ]);
                            $establishmentName = $listing?->lst_name ?? $u->usr_organization_name;
                            $ownerName = $listing?->lst_owner_name ?? $u->usr_name;
                        @endphp
                        <tr
                            data-row
                            data-status="{{ $u->usr_status }}"
                            data-category="{{ $listing?->lst_category }}"
                            data-search-text="{{ strtolower($establishmentName.' '.$ownerName.' '.$u->usr_email.' '.$listing?->lst_barangay) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-sand-900">{{ $establishmentName }}</p>
                                @if ($listing)
                                    <p class="text-xs text-sand-500">{{ \App\Support\TourismCatalog::categoryLabel($listing->lst_category) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $listing?->lst_barangay ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $ownerName }}</td>
                            <td class="px-4 py-3">
                                <p class="text-sand-700">{{ $u->usr_email }}</p>
                                @if ($listing?->lst_contact_phone)
                                    <p class="text-xs text-sand-500">{{ $listing->lst_contact_phone }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($u->usr_status)">{{ $u->usr_status }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right">
                                <div class="relative inline-block">
                                    <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                        <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-44 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                        <button
                                            type="button"
                                            data-modal-open="user-view-{{ $u->usr_id }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                        >
                                            <i class="ti ti-eye" aria-hidden="true"></i> View
                                        </button>
                                        <button
                                            type="button"
                                            data-modal-open="user-form-modal"
                                            data-edit-trigger="user-form-modal"
                                            data-edit-values="{{ $editValues }}"
                                            data-edit-action="{{ route('lgu.users.update', $u->usr_id) }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                        >
                                            <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route('lgu.users.toggleStatus', $u->usr_id) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($u->usr_status === 'Active')
                                                <button
                                                    type="button"
                                                    data-confirm-trigger
                                                    data-confirm-title="Disable {{ $u->usr_name }}?"
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
                                                    data-confirm-title="Enable {{ $u->usr_name }}?"
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

    {{-- Read-only "View" details — every establishment and account field in one place. --}}
    @foreach ($users as $u)
        @php
            $listing = $u->establishment;
            $viewName = $listing?->lst_name ?? $u->usr_organization_name;
            $listingStatusLabel = match ($listing?->lst_status) {
                'DRAFT' => 'Draft',
                'FOR_LGU_REVIEW' => 'Waiting for LGU Review',
                'FOR_PTO_REVIEW' => 'For PTO Review',
                'PUBLISHED' => 'Published',
                'UNPUBLISHED' => 'Unpublished',
                default => $listing?->lst_status,
            };
            $photoCounts = $listing
                ? $listing->establishmentImages->countBy(fn ($image) => $image->img_status->value)
                : collect();
            $viewRows = [
                'Establishment' => [
                    'Name' => $viewName,
                    'Category' => $listing ? \App\Support\TourismCatalog::categoryLabel($listing->lst_category) : null,
                    'Type' => $listing?->lst_type,
                    'Barangay' => $listing?->lst_barangay,
                    'Municipality' => $listing?->lst_municipality,
                    'Business Hours' => $listing?->lst_hours,
                    'Contact Number' => $listing?->lst_contact_phone,
                    'Contact Email' => $listing?->lst_email,
                    'Website / Facebook Page' => $listing?->lst_website,
                    'License Number' => $listing?->lst_license_number,
                    'Accreditation' => $listing?->lst_accreditation_status,
                    'Listing Status' => $listingStatusLabel,
                    'Description' => $listing?->lst_description,
                ],
                'Account' => [
                    'Owner / Manager' => $listing?->lst_owner_name ?? $u->usr_name,
                    'Login Email' => $u->usr_email,
                    'Account Status' => $u->usr_status,
                    'Registered' => $u->usr_created_at?->format('M j, Y g:i A'),
                    'Last Sign-in' => $u->usr_last_login_at?->format('M j, Y g:i A') ?? 'Never signed in',
                ],
            ];
        @endphp
        <x-dashboard.modal id="user-view-{{ $u->usr_id }}" :title="$viewName" max-width="max-w-2xl">
            <div class="flex flex-col gap-5">
                @foreach ($viewRows as $sectionTitle => $rows)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">{{ $sectionTitle }}</h3>
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            @foreach ($rows as $label => $value)
                                <div @class(['sm:col-span-2' => $label === 'Description'])>
                                    <dt class="text-xs font-semibold text-sand-500">{{ $label }}</dt>
                                    <dd class="break-words text-sand-800">{{ filled($value) ? $value : '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach

                <div>
                    <h3 class="mb-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">Photos</h3>
                    <p class="text-sm text-sand-800">
                        {{ $photoCounts->get('PUBLISHED', 0) }} published ·
                        {{ $photoCounts->get('PENDING', 0) }} waiting for approval ·
                        {{ $photoCounts->get('REJECTED', 0) }} returned
                    </p>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Close</button>
            </x-slot:footer>
        </x-dashboard.modal>
    @endforeach

    <x-dashboard.modal id="user-form-modal" title="Add Establishment or Destination" max-width="max-w-2xl">
        <form
            id="user-form"
            method="POST"
            action="{{ route('lgu.users.store') }}"
            data-default-action="{{ route('lgu.users.store') }}"
            data-destination-action="{{ route('lgu.directory.destinations.store') }}"
            data-default-method="POST"
            class="flex flex-col gap-5"
        >
            @csrf
            <div data-entry-type-chooser class="flex flex-col gap-2">
                <span class="text-xs font-semibold text-sand-700">What are you adding?</span>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded-sm border border-sand-300 px-3 py-2.5 text-sm text-sand-800 has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50">
                        <input type="radio" name="entryType" value="establishment" checked class="h-4 w-4 text-primary-700 focus:ring-primary-500">
                        <span><i class="ti ti-building-store" aria-hidden="true"></i> Establishment</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-sm border border-sand-300 px-3 py-2.5 text-sm text-sand-800 has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50">
                        <input type="radio" name="entryType" value="destination" class="h-4 w-4 text-primary-700 focus:ring-primary-500">
                        <span><i class="ti ti-map-pin" aria-hidden="true"></i> Destination</span>
                    </label>
                </div>
                <p data-entry-type-hint class="text-xs text-sand-500">Creates the establishment and its login account. It is listed under Tourism Directory → Establishments.</p>
            </div>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-3 text-xs font-semibold tracking-wide text-sand-500 uppercase"><span data-entry-type-legend>Establishment</span> Details</legend>
                <div>
                    <label for="establishment-name" class="mb-1 block text-xs font-semibold text-sand-700"><span data-entry-type-legend>Establishment</span> Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="establishment-name" name="name" type="text" required maxlength="255" placeholder="e.g. Dahican Surf Resort" data-placeholder-establishment="e.g. Dahican Surf Resort" data-placeholder-destination="e.g. Hamiguitan Range Wildlife Sanctuary" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div data-type-only="destination" hidden>
                        <label for="establishment-category-fixed" class="mb-1 block text-xs font-semibold text-sand-700">Category</label>
                        <input id="establishment-category-fixed" type="text" value="Tourist Destinations" disabled class="w-full rounded-sm border border-sand-200 bg-sand-100 px-3 py-2 text-sm text-sand-500">
                    </div>
                    <div data-type-only="establishment">
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
                        <select id="establishment-barangay" name="barangay" required class="w-full rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm">
                            <option value="" disabled selected>Select barangay</option>
                            @foreach ($barangays as $strBarangay)
                                <option value="{{ $strBarangay }}">{{ $strBarangay }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-sand-500">All {{ count($barangays) }} barangays of {{ $municipality }}.</p>
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
                    <p class="mt-1 text-xs text-sand-500">The closing time is unavailable when "Open 24 hours" is selected.</p>
                </div>
                <div>
                    <label for="establishment-description" class="mb-1 block text-xs font-semibold text-sand-700">Description</label>
                    <textarea id="establishment-description" name="description" rows="3" maxlength="2000" placeholder="Short description of the establishment and its services" data-placeholder-establishment="Short description of the establishment and its services" data-placeholder-destination="Short description of the destination and its attractions" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                </div>
            </fieldset>

            <fieldset class="flex flex-col gap-4 border-t border-sand-200 pt-4">
                <legend class="mb-3 text-xs font-semibold tracking-wide text-sand-500 uppercase">Contact <span data-type-only="establishment">&amp; Account</span></legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="establishment-owner" class="mb-1 block text-xs font-semibold text-sand-700">Owner / Manager Name <span data-required-mark class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-owner" name="ownerName" type="text" data-required-for="establishment" required maxlength="255" placeholder="e.g. Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="establishment-phone" class="mb-1 block text-xs font-semibold text-sand-700">Contact Number <span data-required-mark class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-phone" name="contactPhone" type="tel" data-required-for="establishment" required maxlength="50" placeholder="e.g. 0917 123 4567" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="establishment-email" class="mb-1 block text-xs font-semibold text-sand-700">Email <span data-required-mark class="text-danger" aria-hidden="true">*</span></label>
                        <input id="establishment-email" name="email" type="email" data-required-for="establishment" required maxlength="255" placeholder="e.g. frontdesk@example.com" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        <p data-text-establishment="Used to sign in to the establishment's iTOUR account." data-text-destination="Public contact email. A destination has no login account." class="mt-1 text-xs text-sand-500">Used to sign in to the establishment's iTOUR account.</p>
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

    {{--
        One-time account-created confirmation panel — same shape and JS
        hook (resources/js/user_account.js) as the PTO Add User page.
    --}}
    @if (session('accountCreated'))
        @php($accountCreated = session('accountCreated'))
        <x-dashboard.modal id="account-created-modal" title="Account Created">
            <div class="flex flex-col gap-3">
                <dl class="flex flex-col gap-2 text-sm">
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Name</dt><dd class="text-sand-800">{{ $accountCreated['name'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Role</dt><dd class="text-sand-800">{{ $accountCreated['role'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Municipality</dt><dd class="text-sand-800">{{ $accountCreated['municipality'] }}</dd></div>
                </dl>

                <div class="rounded-md border border-sand-300 bg-sand-50 p-3">
                    <p class="text-xs font-semibold text-sand-700">Temporary Password</p>
                    <div class="mt-1.5 flex items-center justify-between gap-2">
                        <span id="account-created-passphrase" class="font-mono text-sm text-sand-900">{{ $accountCreated['passphrase'] }}</span>
                        <button type="button" id="account-created-copy" class="shrink-0 rounded-sm border border-sand-300 bg-sand-0 px-2.5 py-1 text-xs font-semibold text-sand-800 hover:border-primary-300">Copy</button>
                    </div>
                </div>

                <p class="text-xs text-sand-500">This password will not be shown again.</p>

                <p id="account-created-email-status" @class(['rounded-sm px-3 py-2 text-xs', 'bg-warning-bg text-warning' => ! $accountCreated['emailSent'], 'hidden' => $accountCreated['emailSent']])>
                    Email could not be sent. Please give the temporary password to the user directly.
                </p>
            </div>

            <x-slot:footer>
                @unless ($accountCreated['emailSent'])
                    <button
                        type="button"
                        id="account-created-resend"
                        data-user-id="{{ $accountCreated['userId'] }}"
                        data-resend-url="{{ route('lgu.users.resendWelcomeEmail') }}"
                        class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300"
                    >
                        Send Welcome Email
                    </button>
                @endunless
                <button type="button" data-modal-close class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Done</button>
            </x-slot:footer>
        </x-dashboard.modal>
    @endif

    @vite(['resources/js/user_account.js'])
</x-layouts.dashboard>
