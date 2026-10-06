{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Blade view — establishment / profile.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Merged Establishment Profile & Photos page — status banner, details form,
                 and the reviewed photo manager, in that order, on one scroll.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Establishment Profile"
        description="This is the public tourism information visitors see about {{ $establishmentName }}."
    >
        <x-slot:actions>
            <a href="{{ route('establishment.qr') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-qrcode" aria-hidden="true"></i>
                View QR Code
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    {{-- 1. Status banner --}}
    <x-establishment.status-banner :label="$strStatusLabel" :tone="$strStatusTone" :reason="$strReturnReason" />

    @if (session('arrMissingFields'))
        <div class="mt-4 rounded-md border border-danger/40 bg-danger-bg p-4 text-sm text-danger">
            <p class="font-semibold">A few things are missing before this can be submitted:</p>
            <ul class="mt-1.5 list-disc pl-5">
                @foreach (session('arrMissingFields') as $strMissingField)
                    <li>{{ $strMissingField }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 2. Details form --}}
    <form id="establishment-profile-form" method="POST" action="{{ route('establishment.profile.update') }}" class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        @csrf
        @method('PUT')
        <h2 class="font-display text-base font-bold text-sand-900">Establishment Information</h2>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div data-field class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Establishment Name <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="name" type="text" value="{{ $listing->lst_name }}" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <div data-field>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Category <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="category" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    @foreach ($categories as $c)
                        @if ($c['slug'] !== 'destinations')
                            <option value="{{ $c['slug'] }}" @selected($c['slug'] === $listing->lst_category)>{{ $c['label'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div data-field>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Municipality</label>
                <input type="text" value="{{ $listing->lst_municipality }}" disabled class="w-full rounded-sm border border-sand-200 bg-sand-100 px-3 py-2 text-sm text-sand-500">
                <p class="mt-1 text-[11px] text-sand-500">Municipality changes go through your LGU tourism office.</p>
            </div>

            <div data-field class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Address / Barangay <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="address" type="text" value="{{ $listing->lst_barangay }}" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <div data-field class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-sand-700">Description <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea name="description" rows="3" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">{{ $listing->lst_description }}</textarea>
            </div>

            <div data-field>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Number</label>
                <input name="phone" type="text" value="{{ $listing->lst_contact_phone }}" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <div data-field>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Operating Hours</label>
                <input name="hours" type="text" value="{{ $listing->lst_hours }}" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <div data-field>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Email</label>
                <input name="email" type="email" value="{{ $listing->lst_email }}" placeholder="you@example.com" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                <p class="mt-1 text-[11px] text-sand-500">A public phone number or email is required before submitting.</p>
            </div>

            <div data-field>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Website / Social Media</label>
                <input name="website" type="text" value="{{ $listing->lst_website }}" placeholder="Optional" @disabled($blnIsReadOnly) class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
        </div>
    </form>

    {{-- 3. Photos --}}
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

    @unless ($blnIsReadOnly)
        <div class="mt-6 flex justify-end gap-2">
            <button type="submit" form="establishment-profile-form" class="rounded-sm border border-sand-300 bg-sand-0 px-5 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                Save Draft
            </button>
            <button
                type="button"
                form="establishment-profile-form"
                data-confirm-trigger
                formaction="{{ route('establishment.profile.submit') }}"
                formmethod="PATCH"
                data-confirm-title="Submit to your LGU tourism office?"
                data-confirm-message="Your details and photos will be sent for review. You won't be able to make further changes until it's returned or submitted onward."
                data-confirm-label="Submit"
                class="rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
            >
                Save and Submit to LGU
            </button>
        </div>
    @endunless
</x-layouts.dashboard>
