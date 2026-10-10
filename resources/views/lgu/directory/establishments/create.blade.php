{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU Add Establishment — details and photos in one form. Creates no account and publishes nothing.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Add Establishment"
        description="Register a tourism establishment in {{ $municipality }}. It starts with Manual/Paper reporting; an establishment account and QR code can be set up later if it adopts iTOUR."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments') }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Establishments
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="POST" action="{{ route('lgu.directory.establishments.store') }}" enctype="multipart/form-data" data-listing-form class="mt-6 flex flex-col gap-5">
        @csrf

        @include('lgu.directory.establishments.partials.form-fields', ['listing' => null, 'blnIsContentLocked' => false])
        @include('lgu.directory.establishments.partials.photo-upload')

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="{{ route('lgu.directory.establishments') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save Establishment
            </button>
        </div>
    </form>
</x-layouts.dashboard>
