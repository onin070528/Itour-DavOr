<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Hotlines"
        description="Province-wide emergency and assistance numbers shown on the public Hotlines page. Deactivated numbers are hidden from the public, never deleted."
    >
        <x-slot:actions>
            <button type="button" data-modal-open="hotline-form-modal" data-modal-mode="add" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Add Hotline
            </button>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3"></th>
                    <th class="px-4 py-3">Agency</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Contact Number</th>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @forelse ($hotlines as $hotline)
                    <tr class="hover:bg-sand-50">
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                <form method="POST" action="{{ route('pto.hotlines.reorder', $hotline) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="text-sand-400 hover:text-primary-700" aria-label="Move up"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
                                </form>
                                <form method="POST" action="{{ route('pto.hotlines.reorder', $hotline) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="text-sand-400 hover:text-primary-700" aria-label="Move down"><i class="ti ti-chevron-down" aria-hidden="true"></i></button>
                                </form>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium text-sand-900">{{ $hotline->hot_agency_name }}</td>
                        <td class="px-4 py-3 text-sand-700">{{ $hotline->hot_agency_type }}</td>
                        <td class="px-4 py-3 text-sand-700">{{ $hotline->hot_contact_number }}@if ($hotline->hot_is_24_7) <span class="ml-1 rounded-sm bg-sand-100 px-1.5 py-0.5 text-[11px] text-sand-600">24/7</span>@endif</td>
                        <td class="px-4 py-3 text-sand-700">{{ $hotline->hot_scope }}</td>
                        <td class="px-4 py-3"><x-dashboard.status-badge :tone="$hotline->hot_is_active ? 'success' : 'neutral'">{{ $hotline->hot_is_active ? 'Active' : 'Deactivated' }}</x-dashboard.status-badge></td>
                        <td class="px-4 py-3 text-right">
                            <div class="relative inline-block">
                                <button type="button" data-dropdown-toggle class="text-sand-500 hover:text-sand-800">
                                    <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                </button>
                                <div data-dropdown-menu class="absolute right-0 z-10 mt-1 hidden w-40 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                                    <button
                                        type="button"
                                        data-modal-open="hotline-form-modal"
                                        data-edit-trigger="hotline-form-modal"
                                        data-edit-values="{{ json_encode([
                                            'hot_agency_name' => $hotline->hot_agency_name,
                                            'hot_agency_type' => $hotline->hot_agency_type,
                                            'hot_contact_number' => $hotline->hot_contact_number,
                                            'hot_scope' => $hotline->hot_scope,
                                        ]) }}"
                                        data-edit-action="{{ route('pto.hotlines.update', $hotline) }}"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-sand-700 hover:bg-sand-50"
                                    >
                                        <i class="ti ti-pencil" aria-hidden="true"></i> Edit
                                    </button>
                                    <form method="POST" action="{{ route('pto.hotlines.deactivate', $hotline) }}">
                                        @csrf
                                        @method('PUT')
                                        <button
                                            type="button"
                                            data-confirm-trigger
                                            data-confirm-title="{{ $hotline->hot_is_active ? 'Deactivate' : 'Reactivate' }} {{ $hotline->hot_agency_name }}?"
                                            data-confirm-message="{{ $hotline->hot_is_active ? 'Hidden from the public Hotlines page until reactivated. Never deleted.' : 'Shown on the public Hotlines page again.' }}"
                                            data-confirm-label="{{ $hotline->hot_is_active ? 'Deactivate' : 'Reactivate' }}"
                                            data-confirm-tone="{{ $hotline->hot_is_active ? 'danger' : 'success' }}"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs {{ $hotline->hot_is_active ? 'text-danger hover:bg-danger-bg' : 'text-success hover:bg-success-bg' }}"
                                        >
                                            <i class="ti ti-power" aria-hidden="true"></i> {{ $hotline->hot_is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-sand-500">No hotlines yet — add the first one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-dashboard.modal id="hotline-form-modal" title="Hotline">
        <form
            id="hotline-form"
            method="POST"
            action="{{ route('pto.hotlines.store') }}"
            data-default-action="{{ route('pto.hotlines.store') }}"
            data-default-method="POST"
            class="flex flex-col gap-4"
        >
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Agency Name <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="hot_agency_name" type="text" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Agency Type <span class="text-danger" aria-hidden="true">*</span></label>
                    <select name="hot_agency_type" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        @foreach ($agencyTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Number <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="hot_contact_number" type="text" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Scope</label>
                    <input name="hot_scope" type="text" value="Province-wide" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <label class="mt-6 flex items-center gap-2">
                    <input type="checkbox" name="hot_is_24_7" value="1" class="h-4 w-4 rounded border-sand-300 text-primary-700 focus:ring-primary-500">
                    <span class="text-sm text-sand-800">Open 24/7</span>
                </label>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="hotline-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Save Hotline</button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
