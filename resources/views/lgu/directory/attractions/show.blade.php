{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU tourist attraction details — information, photos, and destination listing state (no account, QR, or reporting; no LGU archive — PTO only).
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@php
    $arrDetails = [
        'Destination type' => $listing->lst_type,
        'Accreditation status' => $listing->lst_accreditation_status,
        'Barangay / Address' => $listing->lst_barangay,
        'Municipality / City' => $listing->lst_municipality,
        'Coordinates' => $listing->lst_lat !== null && $listing->lst_lng !== null ? $listing->lst_lat.', '.$listing->lst_lng : null,
        'Managed by' => $listing->managingLevel()->label(),
        'Managing office' => $listing->lst_contact_office,
        'Contact number' => $listing->lst_contact_phone,
        'Visiting hours' => $listing->lst_hours,
        'Entrance fee' => $listing->lst_entrance_fee,
        'Website or social page' => $listing->lst_website,
    ];
    // Display only — read the managing level directly. A Gate check here
    // would record a security-log denial just for viewing the page; the
    // edit/update routes enforce ListingPolicy::update() themselves.
    $blnCanEdit = ! $listing->isManagedByPto();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="$listing->lst_name"
        description="Tourist attraction · {{ $listing->lst_barangay ? $listing->lst_barangay.', ' : '' }}{{ $listing->lst_municipality }}"
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments', ['view' => 'attractions']) }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Attractions
            </a>
            @if ($blnCanEdit)
                <a href="{{ route('lgu.directory.attractions.edit', $listing) }}" class="btn-primary">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                    Edit Attraction
                </a>
            @endif
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
                        <dd class="detail-value whitespace-pre-line">{{ $listing->lst_description ?: 'No description yet.' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="detail-term">Visitor information</dt>
                        <dd class="detail-value whitespace-pre-line">{{ $listing->lst_visitor_information ?: 'No visitor information yet.' }}</dd>
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
                <p class="mt-2 text-xs text-sand-500">Only the Provincial Tourism Office can suspend or archive an attraction.</p>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
