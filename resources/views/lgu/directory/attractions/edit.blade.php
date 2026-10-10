{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU Edit Tourist Attraction — details form plus the attraction's Photos section (existing photo manager).
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="'Edit '.$listing->lst_name"
        description="Update this attraction's details and photos."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.attractions.show', $listing) }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Details
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="POST" action="{{ route('lgu.directory.attractions.update', $listing) }}" class="mt-6 flex flex-col gap-5">
        @csrf
        @method('PUT')

        @include('lgu.directory.attractions.partials.form-fields', [
            'listing' => $formListing,
            'blnIsContentLocked' => $listing->hasLockedPublicContent(),
            'blnIsLive' => $listing->isPubliclyVisible(),
            'strReviewRemarks' => $listing->hasReturnedPendingChanges() ? $listing->lst_review_remarks : null,
        ])

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="{{ route('lgu.directory.attractions.show', $listing) }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save Changes
            </button>
        </div>
    </form>

    {{-- Photos: the existing photo workflow (its own forms — kept outside the details form). LGU uploads are approved by the PTO. --}}
    <section id="photos" class="mt-8 scroll-mt-6">
        <x-dashboard.photo-manager
            :listing="$listing"
            :images="$listing->establishmentImages"
            :upload-route="route('lgu.images.store')"
            replace-route-name="lgu.images.replace"
            remove-route-name="lgu.images.remove"
            cover-route-name="lgu.images.cover"
            credit-route-name="lgu.images.credit"
            reorder-route-name="lgu.images.reorder"
            :can-upload="true"
        />
    </section>
</x-layouts.dashboard>
