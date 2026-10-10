{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU Establishments page — two views (D1): Establishments (reporting, account, QR, and destination
                 listing state) and Attractions (destination-only records). Add offers both record types.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Establishments"
        description="Tourism records in {{ $municipality }}: establishments (resorts, hotels, restaurants, and more) and tourist attractions such as falls and beaches. Nothing appears on the public site until the PTO approves it."
    >
        <x-slot:actions>
            <div class="relative">
                <button type="button" data-dropdown-toggle class="btn-primary" aria-haspopup="true">
                    <i class="ti ti-plus" aria-hidden="true"></i>
                    Add
                    <i class="ti ti-chevron-down text-xs" aria-hidden="true"></i>
                </button>
                <div data-dropdown-menu class="absolute right-0 z-20 mt-1 hidden w-72 rounded-md border border-sand-200 bg-sand-0 py-1 shadow-md">
                    <a href="{{ route('lgu.directory.establishments.create') }}" class="flex items-start gap-2.5 px-3.5 py-2.5 hover:bg-sand-50">
                        <i class="ti ti-building-store mt-0.5 text-primary-700" aria-hidden="true"></i>
                        <span>
                            <span class="block text-sm font-semibold text-sand-900">Establishment</span>
                            <span class="block text-xs text-sand-500">A business such as a resort, hotel, or restaurant.</span>
                        </span>
                    </a>
                    <a href="{{ route('lgu.directory.attractions.create') }}" class="flex items-start gap-2.5 px-3.5 py-2.5 hover:bg-sand-50">
                        <i class="ti ti-mountain mt-0.5 text-primary-700" aria-hidden="true"></i>
                        <span>
                            <span class="block text-sm font-semibold text-sand-900">Tourist Attraction</span>
                            <span class="block text-xs text-sand-500">Destination only, such as falls or a beach. No account, QR, or reporting.</span>
                        </span>
                    </a>
                </div>
            </div>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($intPhotoReviewCount > 0)
        @include('lgu.directory.establishments.partials.photo-review-notice')
    @endif

    {{-- Summary: the two views of this page. --}}
    <nav class="mt-6 flex gap-1 border-b border-sand-200" aria-label="Record type">
        <a href="{{ route('lgu.directory.establishments') }}" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-primary-700 text-primary-700' => ! $blnIsAttractionsView, 'border-transparent text-sand-500 hover:text-sand-800' => $blnIsAttractionsView]) @if (! $blnIsAttractionsView) aria-current="page" @endif>
            Establishments <span class="ml-1 text-xs font-normal">{{ $establishments->count() }}</span>
        </a>
        <a href="{{ route('lgu.directory.establishments', ['view' => 'attractions']) }}" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-primary-700 text-primary-700' => $blnIsAttractionsView, 'border-transparent text-sand-500 hover:text-sand-800' => ! $blnIsAttractionsView]) @if ($blnIsAttractionsView) aria-current="page" @endif>
            Attractions <span class="ml-1 text-xs font-normal">{{ $attractions->count() }}</span>
        </a>
    </nav>

    @if ($blnIsAttractionsView)
        @include('lgu.directory.attractions.partials.table')
    @else
        <div data-filterable-table data-page-size="10" class="mt-4">
            @include('lgu.directory.establishments.partials.filters')

            @if ($establishments->isNotEmpty())
                <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $establishments->count() }}</span> of {{ $establishments->count() }} establishments</p>

                @include('lgu.directory.establishments.partials.table')

                {{-- QR view / print / download, plus the on/off switch (ListingPolicy::manageQr()). --}}
                @foreach ($establishments as $establishment)
                    <x-dashboard.qr-modal :listing="$establishment" :can-manage="$user->can('manageQr', $establishment)" />
                @endforeach
            @endif

            <x-dashboard.empty-state
                data-empty-state
                class="{{ $establishments->isNotEmpty() ? 'hidden' : '' }} mt-3"
                icon="ti-building-store"
                title="{{ $establishments->isNotEmpty() ? 'No establishments match your filters' : 'No establishments in '.$municipality.' yet' }}"
                description="{{ $establishments->isNotEmpty() ? 'Try a different search or clear the filters.' : 'Use Add → Establishment to register the first one.' }}"
            />

            <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
        </div>
    @endif
</x-layouts.dashboard>
