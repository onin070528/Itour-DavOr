{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Destination account's Photos page — upload and manage the destination's photos, sent to the LGU for approval.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Photos"
        description="Upload photos of {{ $establishmentName }}. Each new photo is sent to your LGU for approval before it appears publicly."
    />

    <section id="photos" class="mt-6">
        <x-dashboard.photo-manager
            :listing="$listing"
            :images="$images"
            :upload-route="route('establishment.images.store')"
            replace-route-name="establishment.images.replace"
            remove-route-name="establishment.images.remove"
            cover-route-name="establishment.images.cover"
            credit-route-name="establishment.images.credit"
            reorder-route-name="establishment.images.reorder"
            :read-only="$blnIsReadOnly"
        />
    </section>
</x-layouts.dashboard>
