{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU Add Tourist Attraction (destination-only record) — details and photos in one form.
                 No account, QR, or reporting; it goes public only after PTO approval.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Add Tourist Attraction"
        description="Register a destination in {{ $municipality }} such as falls, a beach, or a viewpoint. It has no establishment account, QR code, or monthly reporting, and appears publicly only after the PTO approves it."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments', ['view' => 'attractions']) }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Attractions
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="POST" action="{{ route('lgu.directory.attractions.store') }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-5">
        @csrf

        @include('lgu.directory.attractions.partials.form-fields', ['listing' => null, 'blnIsContentLocked' => false])
        @include('lgu.directory.establishments.partials.photo-upload')

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="{{ route('lgu.directory.establishments', ['view' => 'attractions']) }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save Attraction
            </button>
        </div>
    </form>
</x-layouts.dashboard>
