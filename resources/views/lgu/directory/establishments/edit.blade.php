{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU Edit Establishment — details form plus the establishment's Photos section (existing photo manager).
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="'Edit '.$listing->name"
        description="Update this establishment's details and photos."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments.show', $listing) }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Details
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="POST" action="{{ route('lgu.directory.establishments.update', $listing) }}" data-listing-form class="mt-6 flex flex-col gap-5">
        @csrf
        @method('PUT')

        @include('lgu.directory.establishments.partials.form-fields', ['listing' => $formListing, 'blnIsContentLocked' => $listing->hasLockedPublicContent(), 'blnIsPublished' => $listing->isPublished(), 'strReviewRemarks' => $listing->hasReturnedPendingChanges() ? $listing->lst_review_remarks : null])

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="{{ route('lgu.directory.establishments.show', $listing) }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save Changes
            </button>
        </div>
    </form>

    {{-- Photos: the existing photo workflow (its own forms — kept outside the details form). --}}
    <section id="photos" class="mt-8 scroll-mt-6">
        @unless ($blnCanUploadPhotos)
            <p class="mb-3 text-xs text-sand-500">This establishment manages its own photos through its iTOUR account. You can still set the cover, reorder, or remove photos here; new photos it uploads come to you for approval.</p>
        @endunless

        <x-dashboard.photo-manager
            :listing="$listing"
            :images="$listing->establishmentImages"
            :upload-route="route('lgu.images.store')"
            replace-route-name="lgu.images.replace"
            remove-route-name="lgu.images.remove"
            cover-route-name="lgu.images.cover"
            credit-route-name="lgu.images.credit"
            reorder-route-name="lgu.images.reorder"
            :can-upload="$blnCanUploadPhotos"
        />
    </section>
</x-layouts.dashboard>
