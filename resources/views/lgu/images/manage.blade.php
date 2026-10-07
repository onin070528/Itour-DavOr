{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU photo management for one establishment in its own municipality.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="$listing->lst_name.' — Photos'"
        description="Replacements and new uploads on behalf of a no-account/paper establishment are reviewed by the PTO. Removing, setting a cover, and reordering apply immediately."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Establishments
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6">
        <x-dashboard.photo-manager
            :listing="$listing"
            :images="$images"
            :upload-route="route('lgu.images.store')"
            replace-route-name="lgu.images.replace"
            remove-route-name="lgu.images.remove"
            cover-route-name="lgu.images.cover"
            credit-route-name="lgu.images.credit"
            reorder-route-name="lgu.images.reorder"
            :can-upload="$canUpload"
        />
    </div>
</x-layouts.dashboard>
