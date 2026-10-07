{{-- Establishments table (R12). Reporting, account, and destination listing are plain text (no status badges, R12); the QR cell is the shared <x-dashboard.qr-cell>. --}}
<div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
    <table class="w-full min-w-[960px] border-collapse text-sm">
        <thead>
            <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                <th class="px-4 py-3">Establishment</th>
                <th class="px-4 py-3">Category / Type</th>
                <th class="px-4 py-3">Location</th>
                <th class="px-4 py-3">Reporting</th>
                <th class="px-4 py-3">Account</th>
                <th class="px-4 py-3">QR</th>
                <th class="px-4 py-3">Destination Listing</th>
                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-sand-100">
            @foreach ($establishments as $establishment)
                @php($objReportingMethod = $establishment->reportingMethod())
                <tr
                    data-row
                    data-category-id="{{ $establishment->cat_id }}"
                    data-type="{{ $establishment->type }}"
                    data-reporting="{{ $objReportingMethod->value }}"
                    data-search-text="{{ Str::lower($establishment->name.' '.$establishment->barangay) }}"
                    class="hover:bg-sand-50"
                >
                    <td class="px-4 py-3">
                        <a href="{{ route('lgu.directory.establishments.show', $establishment) }}" class="font-medium text-sand-900 hover:text-primary-700">{{ $establishment->name }}</a>
                    </td>
                    <td class="px-4 py-3 text-sand-700">
                        {{ $establishment->categoryName() }}
                        <span class="block text-xs text-sand-500">{{ $establishment->type ?? 'Type not set' }}</span>
                    </td>
                    <td class="px-4 py-3 text-sand-700">{{ $establishment->barangay ?: '—' }}</td>
                    <td class="px-4 py-3 text-sand-700">
                        <span class="inline-flex items-center gap-1.5">
                            <i class="ti {{ $objReportingMethod->icon() }} text-sand-500" aria-hidden="true"></i>
                            {{ $objReportingMethod->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sand-700">{{ $establishment->accountStatusLabel() }}</td>
                    <td class="px-4 py-3"><x-dashboard.qr-cell :listing="$establishment" /></td>
                    <td class="px-4 py-3 text-sand-700">{{ $establishment->destinationListingLabel() }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('lgu.directory.establishments.show', $establishment) }}" class="btn-small">View</a>
                            <a href="{{ route('lgu.directory.establishments.edit', $establishment) }}" class="btn-small">Edit</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
