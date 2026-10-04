{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : PTO photo management for any establishment, province-wide.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        :title="$listing->name.' — Photos'"
        description="Uploads made here publish immediately. Removing, setting a cover, and reordering apply immediately."
    >
        <x-slot:actions>
            <a href="{{ route('pto.directory.index') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Directory
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6">
        <x-dashboard.photo-manager
            :listing="$listing"
            :images="$images"
            :upload-route="route('pto.images.store')"
            replace-route-name="pto.images.replace"
            remove-route-name="pto.images.remove"
            cover-route-name="pto.images.cover"
            credit-route-name="pto.images.credit"
            reorder-route-name="pto.images.reorder"
            :can-upload="$canUpload"
        />
    </div>
</x-layouts.dashboard>
