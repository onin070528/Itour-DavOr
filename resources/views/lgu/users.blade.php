{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU Establishment Accounts — the municipality's establishment accounts: edit the account, enable/disable it.
                 Accounts are created from the establishment (Tourism Directory -> Establishments -> Activate Online iTOUR account).
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@php
    $statusTone = fn ($status) => $status === 'Active' ? 'success' : 'danger';
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Establishment Accounts"
        :description="'iTOUR accounts of establishments in '.$municipality.'. To give an establishment an account, open it under Establishments and choose Activate Online iTOUR account.'"
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments') }}" class="btn-primary">
                <i class="ti ti-building-store" aria-hidden="true"></i>
                Go to Establishments
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by establishment, account holder, email, or barangay..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="category-id" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->cat_id }}">{{ $category->cat_name }}</option>
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
                        <th class="px-4 py-3">Account Holder</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($users as $u)
                        @php
                            $listing = $u->establishment;
                            $editValues = json_encode(['name' => $u->usr_name, 'email' => $u->usr_email]);
                            $establishmentName = $listing?->lst_name ?? $u->usr_organization_name;
                        @endphp
                        <tr
                            data-row
                            data-status="{{ $u->usr_status }}"
                            data-category-id="{{ $listing?->cat_id }}"
                            data-search-text="{{ strtolower($establishmentName.' '.$u->usr_name.' '.$u->usr_email.' '.$listing?->lst_barangay) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3">
                                @if ($listing)
                                    <a href="{{ route('lgu.directory.establishments.show', $listing) }}" class="font-medium text-sand-900 hover:text-primary-700">{{ $establishmentName }}</a>
                                    <p class="text-xs text-sand-500">{{ $listing->categoryName() }} · {{ $listing->reportingMethod()->label() }}</p>
                                @else
                                    <p class="font-medium text-sand-900">{{ $establishmentName }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $listing?->lst_barangay ?: '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $u->usr_name }}</td>
                            <td class="px-4 py-3">
                                <p class="text-sand-700">{{ $u->usr_email }}</p>
                                @if ($listing?->lst_contact_phone)
                                    <p class="text-xs text-sand-500">{{ $listing->lst_contact_phone }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($u->usr_status)">{{ $u->usr_status }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right">
                                <div class="relative inline-block">
                                    <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800" aria-label="Account actions">
                                        <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-48 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                        <button
                                            type="button"
                                            data-modal-open="user-form-modal"
                                            data-edit-trigger="user-form-modal"
                                            data-edit-values="{{ $editValues }}"
                                            data-edit-action="{{ route('lgu.users.update', $u->usr_id) }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                        >
                                            <i class="ti ti-pencil" aria-hidden="true"></i> Edit Account
                                        </button>
                                        @if ($listing)
                                            <a href="{{ route('lgu.directory.establishments.show', $listing) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                                <i class="ti ti-building-store" aria-hidden="true"></i> Open Establishment
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('lgu.users.toggleStatus', $u->usr_id) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($u->usr_status === 'Active')
                                                <button
                                                    type="button"
                                                    data-confirm-trigger
                                                    data-confirm-title="Disable {{ $u->usr_name }}?"
                                                    data-confirm-message="They will immediately lose access to their iTOUR account, and QR check-in stops."
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
            class="{{ $users->isNotEmpty() ? 'hidden' : '' }} mt-3"
            icon="ti-users"
            title="{{ $users->isNotEmpty() ? 'No accounts match your filters' : 'No establishment accounts yet' }}"
            description="{{ $users->isNotEmpty() ? 'Try a different status or category.' : 'Open an establishment under Establishments and choose Activate Online iTOUR account.' }}"
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>

    {{-- Edit the account itself (name and sign-in email). Establishment details are edited on the establishment page. --}}
    <x-dashboard.modal id="user-form-modal" title="Edit Account">
        <form id="user-form" method="POST" action="" class="flex flex-col gap-4">
            @csrf
            @method('PUT')
            <div>
                <label for="account-holder-name" class="form-label">Account holder's name <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="account-holder-name" name="name" type="text" required maxlength="255" class="form-input">
            </div>
            <div>
                <label for="account-holder-email" class="form-label">Sign-in email <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="account-holder-email" name="email" type="email" required maxlength="255" class="form-input">
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">Cancel</button>
            <button type="submit" form="user-form" class="btn-primary">Save</button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
