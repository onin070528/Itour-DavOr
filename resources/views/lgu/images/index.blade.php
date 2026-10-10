{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Photos page — approval queue only. Establishments upload their own photos from their
    accounts; the LGU approves or returns them here.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header title="Photos" description="Review photos establishments uploaded from their accounts. Approve to publish them, or return with a reason." />

    <div class="mt-6 flex flex-col gap-4">
        @forelse ($cards as $card)
            <x-dashboard.photo-approval-card :card="$card" approve-all-route-name="lgu.images.approveBatch" return-batch-route-name="lgu.images.returnBatch" />
        @empty
            <x-dashboard.empty-state icon="ti-photo-check" title="Nothing waiting for approval" description="Photos establishments upload will show up here." />
        @endforelse
    </div>
</x-layouts.dashboard>
