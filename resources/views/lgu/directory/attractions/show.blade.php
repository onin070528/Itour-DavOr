{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU tourist attraction details — information, photos, and destination listing state (no account, QR, or reporting).
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@php
    $arrDetails = [
        'Barangay / Address' => $listing->barangay,
        'Municipality / City' => $listing->municipality,
        'Coordinates' => $listing->lat !== null && $listing->lng !== null ? $listing->lat.', '.$listing->lng : null,
        'Contact office' => $listing->contact_office,
        'Contact number' => $listing->contact_phone,
        'Visiting hours' => $listing->hours,
        'Website or social page' => $listing->website,
    ];
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="$listing->name"
        description="Tourist attraction · {{ $listing->barangay ? $listing->barangay.', ' : '' }}{{ $listing->municipality }}"
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments', ['view' => 'attractions']) }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Attractions
            </a>
            <a href="{{ route('lgu.directory.attractions.edit', $listing) }}" class="btn-primary">
                <i class="ti ti-edit" aria-hidden="true"></i>
                Edit Attraction
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="flex flex-col gap-5 lg:col-span-2">
            <section class="dashboard-panel">
                <h2 class="dashboard-panel-title">Attraction information</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($arrDetails as $strTerm => $strValue)
                        @continue($strValue === null || $strValue === '')
                        <div>
                            <dt class="detail-term">{{ $strTerm }}</dt>
                            <dd class="detail-value">{{ $strValue }}</dd>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <dt class="detail-term">Description</dt>
                        <dd class="detail-value whitespace-pre-line">{{ $listing->description ?: 'No description yet.' }}</dd>
                    </div>
                </dl>
            </section>

            @include('lgu.directory.establishments.partials.photo-summary')
        </div>

        <div class="flex flex-col gap-5">
            @include('lgu.directory.establishments.partials.destination-panel')

            <section class="dashboard-panel">
                <h2 class="dashboard-panel-title">Destination only</h2>
                <p class="mt-2 text-xs text-sand-500">Tourist attractions have no establishment account, QR code, or monthly reporting. Arrivals are reported by the establishments around them.</p>
                @unless ($listing->status === 'Archived')
                    <form method="POST" action="{{ route('lgu.directory.destinations.archive', $listing) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <button
                            type="button"
                            data-confirm-trigger
                            data-confirm-title="Archive {{ $listing->name }}?"
                            data-confirm-message="It is taken off the public site. The record, its photos, and its history are kept."
                            data-confirm-label="Archive"
                            data-confirm-tone="danger"
                            class="btn-secondary w-full justify-center border-danger/30 text-danger hover:bg-danger-bg"
                        >
                            <i class="ti ti-archive" aria-hidden="true"></i>
                            Archive attraction
                        </button>
                    </form>
                @endunless
            </section>
        </div>
    </div>
</x-layouts.dashboard>
