{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public emergency hotlines page.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.public title="Hotlines">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl">Province-Wide Hotlines</h1>
        <p class="mt-2 text-sm text-sand-600">
            Emergency and assistance numbers maintained by the Provincial Tourism Office, grouped by agency type. Keep this page saved — it still opens even without a signal.
        </p>

        @if ($hotlines->isEmpty())
            <x-dashboard.empty-state
                class="mt-8"
                icon="ti-phone-off"
                title="No hotlines published yet"
                description="Check back soon — the Provincial Tourism Office is still setting these up."
            />
        @else
            <div class="mt-8 flex flex-col gap-6">
                @foreach ($hotlines as $agencyType => $group)
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
                        <h2 class="font-display text-base font-bold text-sand-900">{{ $agencyType }}</h2>
                        <ul class="mt-3 flex flex-col divide-y divide-sand-100">
                            @foreach ($group as $hotline)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-sand-900">{{ $hotline->hot_agency_name }}</p>
                                        <p class="text-xs text-sand-500">
                                            {{ $hotline->hot_scope }}
                                            @if ($hotline->hot_is_24_7)
                                                · <span class="font-semibold text-success">Open 24/7</span>
                                            @endif
                                        </p>
                                    </div>
                                    <a href="tel:{{ $hotline->hot_contact_number }}" class="inline-flex items-center gap-1.5 rounded-sm border border-primary-300 bg-primary-100 px-3 py-1.5 text-sm font-semibold text-primary-700 hover:bg-primary-100/70">
                                        <i class="ti ti-phone" aria-hidden="true"></i>
                                        {{ $hotline->hot_contact_number }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
