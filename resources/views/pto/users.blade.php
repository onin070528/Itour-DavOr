@php
    $roles = collect($users)->pluck('role')->unique()->sort()->values();
    $statusTone = fn ($status) => $status === 'Active' ? 'success' : 'danger';
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="User Management"
        description="PTO, LGU, and Establishment accounts registered in iTOUR."
    >
        <x-slot:actions>
            <button type="button" data-modal-open="user-form-modal" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Add User
            </button>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="8" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by name or email..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="role" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Roles</option>
                @foreach ($roles as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
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

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($users) }}</span> of {{ count($users) }} accounts</p>

        <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[760px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Assigned To</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($users as $u)
                        @php
                            $editValues = json_encode([
                                'name' => $u['name'],
                                'email' => $u['email'],
                                'role' => $u['roleValue'],
                                'municipality_id' => $u['municipalityId'],
                                'establishment_id' => $u['establishmentId'],
                                'phone' => $u['phone'],
                            ]);
                        @endphp
                        <tr
                            data-row
                            data-role="{{ $u['role'] }}"
                            data-status="{{ $u['status'] }}"
                            data-search-text="{{ strtolower($u['name'].' '.$u['email']) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $u['name'] }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $u['email'] }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $u['role'] }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $u['assignment'] }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($u['status'])">{{ $u['status'] }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-right">
                                <div class="relative inline-block">
                                    <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                        <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-44 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                        <button type="button" data-modal-open="user-view-modal-{{ $loop->index }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50">
                                            <i class="ti ti-eye" aria-hidden="true"></i> View Details
                                        </button>
                                        <button
                                            type="button"
                                            data-modal-open="user-form-modal"
                                            data-edit-trigger="user-form-modal"
                                            data-edit-values="{{ $editValues }}"
                                            data-edit-action="{{ route('pto.users.update', $u['id']) }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                        >
                                            <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route('pto.users.toggleStatus', $u['id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($u['status'] === 'Active')
                                                <button
                                                    type="button"
                                                    data-confirm-trigger
                                                    data-confirm-title="Disable {{ $u['name'] }}?"
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
                                                    data-confirm-title="Enable {{ $u['name'] }}?"
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

        @foreach ($users as $u)
            <x-dashboard.modal id="user-view-modal-{{ $loop->index }}" :title="$u['name']">
                <dl class="flex flex-col gap-3 text-sm">
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Email</dt><dd class="text-sand-800">{{ $u['email'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Role</dt><dd class="text-sand-800">{{ $u['role'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Assigned Municipality / Establishment</dt><dd class="text-sand-800">{{ $u['assignment'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt><dd><x-dashboard.status-badge :tone="$statusTone($u['status'])">{{ $u['status'] }}</x-dashboard.status-badge></dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Last Active</dt><dd class="text-sand-800">{{ \Illuminate\Support\Carbon::parse($u['lastActive'])->format('M j, Y') }}</dd></div>
                </dl>
            </x-dashboard.modal>
        @endforeach

        <x-dashboard.empty-state
            data-empty-state
            class="hidden mt-3"
            icon="ti-users"
            title="No users match your filters"
            description="Try a different role or status."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>

    <x-dashboard.modal id="user-form-modal" title="User">
        <form
            id="user-form"
            method="POST"
            action="{{ route('pto.users.store') }}"
            data-default-action="{{ route('pto.users.store') }}"
            data-default-method="POST"
            data-establishments-url="{{ route('pto.users.availableEstablishments') }}"
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
                <label class="mb-1 block text-xs font-semibold text-sand-700">Role <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="role" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="{{ \App\Enums\UserRole::Lgu->value }}">{{ \App\Enums\UserRole::Lgu->title() }}</option>
                    <option value="{{ \App\Enums\UserRole::Establishment->value }}">{{ \App\Enums\UserRole::Establishment->title() }}</option>
                    <option value="{{ \App\Enums\UserRole::PtoAdministrator->value }}">{{ \App\Enums\UserRole::PtoAdministrator->title() }}</option>
                </select>
            </div>
            <div id="user-form-municipality-field">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Municipality <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="municipality_id" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="">Select a municipality</option>
                    @foreach ($municipalities as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="user-form-establishment-field" hidden>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Establishment <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="establishment_id" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="" disabled selected>Select a municipality first</option>
                </select>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="user-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Save User
            </button>
        </x-slot:footer>
    </x-dashboard.modal>

    {{--
        One-time account-created confirmation panel. Opened automatically
        by resources/js/user_account.js when `accountCreated` was flashed —
        gone on the very next page load/reload, never stored client-side.
    --}}
    @if (session('accountCreated'))
        @php($accountCreated = session('accountCreated'))
        <x-dashboard.modal id="account-created-modal" title="Account Created">
            <div class="flex flex-col gap-3">
                <dl class="flex flex-col gap-2 text-sm">
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Name</dt><dd class="text-sand-800">{{ $accountCreated['name'] }}</dd></div>
                    <div><dt class="text-xs font-semibold text-sand-500 uppercase">Role</dt><dd class="text-sand-800">{{ $accountCreated['role'] }}</dd></div>
                    @if ($accountCreated['municipality'])
                        <div><dt class="text-xs font-semibold text-sand-500 uppercase">Municipality</dt><dd class="text-sand-800">{{ $accountCreated['municipality'] }}</dd></div>
                    @endif
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
                        data-resend-url="{{ route('pto.users.resendWelcomeEmail') }}"
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
