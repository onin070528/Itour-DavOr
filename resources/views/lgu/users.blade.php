@php
    $statusTone = fn ($status) => $status === 'Active' ? 'success' : 'danger';
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Users"
        :description="'Establishment accounts registered in '.$municipality.'.'"
    >
        <x-slot:actions>
            @if ($establishments->isNotEmpty())
                <button type="button" data-modal-open="user-form-modal" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    <i class="ti ti-plus" aria-hidden="true"></i>
                    Add Establishment Account
                </button>
            @endif
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by name or email..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
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
            <table class="w-full min-w-[680px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Establishment</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($users as $u)
                        @php
                            $editValues = json_encode(['name' => $u->name, 'email' => $u->email]);
                        @endphp
                        <tr
                            data-row
                            data-status="{{ $u->status }}"
                            data-search-text="{{ strtolower($u->name.' '.$u->email) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $u->name }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $u->email }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $u->organization_name }}</td>
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

    @if ($establishments->isEmpty() && $users->isEmpty())
        <p class="mt-4 text-sm text-sand-500">There are no establishments registered in {{ $municipality }} yet — add one under Tourism Directory before creating an account for it.</p>
    @endif

    <x-dashboard.modal id="user-form-modal" title="Establishment Account">
        <form
            id="user-form"
            method="POST"
            action="{{ route('lgu.users.store') }}"
            data-default-action="{{ route('lgu.users.store') }}"
            data-default-method="POST"
            class="flex flex-col gap-4"
        >
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Full Name <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="name" type="text" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Email <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="email" type="email" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Establishment <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="establishment_id" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="">Select an establishment...</option>
                    @foreach ($establishments as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-sand-500">Only when adding a new account — ignored when editing an existing one. Only establishments in {{ $municipality }} without an existing account are shown.</p>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="user-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Save
            </button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
