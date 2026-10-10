{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : PTO Destination Listing Reviews — LGU requests to feature establishments (and changes to Published listings)
                 waiting for a decision, plus the ones returned to their LGU for correction.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Destination Listing Reviews"
        description="LGU requests to feature establishments as tourist destinations. Nothing is public until you approve it."
    >
        <x-slot:actions>
            <a href="{{ route('pto.directory.index') }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Tourism Directory
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <section class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Waiting for your decision ({{ $awaitingListings->count() }})</h2>
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3">Listing</th>
                    <th class="px-4 py-3">Municipality</th>
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Waiting since</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @forelse ($awaitingListings as $listing)
                    <tr class="hover:bg-sand-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-sand-900">{{ $listing->lst_name }}</p>
                            <p class="text-xs text-sand-500">{{ $listing->categoryName() }}{{ $listing->lst_type ? ' · '.$listing->lst_type : '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-sand-700">{{ $listing->lst_municipality }}</td>
                        <td class="px-4 py-3">
                            <x-dashboard.status-badge tone="warning">{{ $listing->hasPendingChanges() ? 'Changes to a published listing' : 'New destination listing' }}</x-dashboard.status-badge>
                        </td>
                        <td class="px-4 py-3 text-sand-700">{{ $listing->lst_updated_at?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('pto.destinationReviews.show', $listing) }}" class="btn-primary btn-small">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sand-500">No destination listings are waiting for review.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($returnedListings->isNotEmpty())
        <section class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Returned to the LGU for correction ({{ $returnedListings->count() }})</h2>
            <ul class="divide-y divide-sand-100">
                @foreach ($returnedListings as $listing)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                        <span>
                            <span class="font-medium text-sand-900">{{ $listing->lst_name }}</span>
                            <span class="text-xs text-sand-500">· {{ $listing->lst_municipality }}</span>
                            <span class="mt-0.5 block text-xs text-sand-600">Remarks: {{ $listing->lst_review_remarks }}</span>
                        </span>
                        <a href="{{ route('pto.destinationReviews.show', $listing) }}" class="text-xs font-semibold text-primary-700 hover:text-primary-900">View</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.dashboard>
