{{--
    Attractions view of the Establishments page (D1): the municipality's destination-only records.
    No reporting, account, or QR columns — attractions have none. Expects $attractions, $municipality.
--}}
<div data-filterable-table data-page-size="10" class="mt-4">
    <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 sm:flex-row sm:items-center">
        <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
            <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
            <input data-filter-input type="search" placeholder="Search attractions by name or barangay..." aria-label="Search attractions" class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
        </div>
        <button type="button" data-filter-reset class="btn-secondary">Reset</button>
    </div>

    @if ($attractions->isNotEmpty())
        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $attractions->count() }}</span> of {{ $attractions->count() }} attractions</p>

        <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[720px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Attraction</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Photos</th>
                        <th class="px-4 py-3">Destination Listing</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($attractions as $attraction)
                        <tr data-row data-search-text="{{ Str::lower($attraction->name.' '.$attraction->barangay) }}" class="hover:bg-sand-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('lgu.directory.attractions.show', $attraction) }}" class="font-medium text-sand-900 hover:text-primary-700">{{ $attraction->name }}</a>
                                <span class="block text-xs text-sand-500">Tourist attraction</span>
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $attraction->barangay ?: '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $attraction->intPhotoCount }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $attraction->destinationListingLabel() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('lgu.directory.attractions.show', $attraction) }}" class="btn-small">View</a>
                                    <a href="{{ route('lgu.directory.attractions.edit', $attraction) }}" class="btn-small">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-dashboard.empty-state
        data-empty-state
        class="{{ $attractions->isNotEmpty() ? 'hidden' : '' }} mt-3"
        icon="ti-mountain"
        title="{{ $attractions->isNotEmpty() ? 'No attractions match your search' : 'No tourist attractions in '.$municipality.' yet' }}"
        description="{{ $attractions->isNotEmpty() ? 'Try a different search.' : 'Use Add → Tourist Attraction to register falls, beaches, and other destinations.' }}"
    />

    <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
</div>
