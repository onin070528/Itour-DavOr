{{--
    Establishment detail fields shared by the Add and Edit pages (R14).
    Expects: $categories (establishment categories only), $listing (null on Add),
    $blnIsContentLocked (public destination content locked while a new request is with the PTO),
    optional $blnIsPublished (edits to public content are held for PTO review) and
    $strReviewRemarks (the PTO's remarks on returned held changes).
    The municipality is shown, never submitted — the server always uses the LGU's own.
--}}
@php
    $blnIsPublished ??= false;
    $strReviewRemarks ??= null;
    $strLockedNote = 'Locked while this establishment\'s destination listing request is with the PTO.';
    $fnLockedAttribute = fn (string $strField) => $blnIsContentLocked && in_array($strField, \App\Models\Listing::PUBLIC_CONTENT_FIELDS, true);
@endphp

@if ($blnIsContentLocked)
    <p class="flex items-start gap-2 rounded-sm border border-sand-200 bg-sand-50 px-3 py-2.5 text-xs text-sand-600">
        <i class="ti ti-lock mt-0.5" aria-hidden="true"></i>
        <span>Name, category, type, location, and description are part of the destination listing the PTO is reviewing, so they can't be changed until it decides. Contact and operating details can still be updated.</span>
    </p>
@elseif ($blnIsPublished)
    <div class="flex flex-col gap-2 rounded-sm border border-primary-300 bg-primary-100 px-3 py-2.5 text-xs text-primary-900">
        <p class="flex items-start gap-2">
            <i class="ti ti-world mt-0.5" aria-hidden="true"></i>
            <span>This establishment is a published tourist destination. Changes to its name, category, type, location, or description are sent to the PTO for review, and the published version stays on the public site until they are approved. Contact and operating details save straight away.</span>
        </p>
        @if ($strReviewRemarks)
            <p class="rounded-sm border border-warning/30 bg-warning-bg px-2.5 py-2 text-warning"><span class="font-semibold">PTO remarks:</span> {{ $strReviewRemarks }}</p>
        @endif
    </div>
@endif

{{-- Summary: basic information --}}
<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Basic information</h2>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="establishment-name" class="form-label">Establishment name <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="establishment-name" name="name" type="text" maxlength="255" required value="{{ old('name', $listing?->lst_name) }}" @disabled($fnLockedAttribute('name')) @if ($fnLockedAttribute('name')) title="{{ $strLockedNote }}" @endif class="form-input">
            @error('name') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-category" class="form-label">Category <span class="text-danger" aria-hidden="true">*</span></label>
            <select id="establishment-category" name="cat_id" required @disabled($fnLockedAttribute('cat_id')) class="form-input">
                <option value="">Select a category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->cat_id }}" data-category-name="{{ $category->cat_name }}" @selected((string) old('cat_id', $listing?->cat_id) === (string) $category->cat_id)>{{ $category->cat_name }}</option>
                @endforeach
            </select>
            @error('cat_id') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-type" class="form-label">Establishment type <span class="text-danger" aria-hidden="true">*</span></label>
            <x-dashboard.establishment-type-select id="establishment-type" :categories="$categories" :selected="old('type', $listing?->lst_type)" :disabled="$fnLockedAttribute('type')" :data-locked="$fnLockedAttribute('type') ? 'true' : null" />
            <p class="form-hint">Choose the category first.</p>
            @error('type') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div data-show-when="others" class="hidden sm:col-span-2">
            <label for="establishment-category-note" class="form-label">Describe the establishment <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="establishment-category-note" name="category_note" type="text" maxlength="255" value="{{ old('category_note', $listing?->lst_category_note) }}" @disabled($fnLockedAttribute('category_note')) class="form-input">
            @error('category_note') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-owner" class="form-label">Owner / Manager</label>
            <input id="establishment-owner" name="owner_name" type="text" maxlength="255" value="{{ old('owner_name', $listing?->lst_owner_name) }}" class="form-input">
            @error('owner_name') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-accreditation" class="form-label">Accreditation status</label>
            <input id="establishment-accreditation" name="accreditation_status" type="text" maxlength="255" placeholder="e.g. DOT-accredited" value="{{ old('accreditation_status', $listing?->lst_accreditation_status) }}" class="form-input">
            @error('accreditation_status') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div data-show-when="guide" class="hidden sm:col-span-2">
            <label for="establishment-license" class="form-label">License number <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="establishment-license" name="license_number" type="text" maxlength="255" value="{{ old('license_number', $listing?->lst_license_number) }}" class="form-input">
            @error('license_number') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="establishment-description" class="form-label">Description</label>
            <textarea id="establishment-description" name="description" rows="4" @disabled($fnLockedAttribute('description')) class="form-input">{{ old('description', $listing?->lst_description) }}</textarea>
            @error('description') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- Summary: location --}}
<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Location</h2>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <span class="form-label">Municipality / City</span>
            <p class="form-input bg-sand-50 text-sand-600" aria-readonly="true">{{ $municipality }}</p>
            <p class="form-hint">Assigned from your LGU account.</p>
        </div>

        <div>
            <label for="establishment-barangay" class="form-label">Barangay / Address <span data-hide-when="guide" class="text-danger" aria-hidden="true">*</span></label>
            <input id="establishment-barangay" name="barangay" type="text" maxlength="255" value="{{ old('barangay', $listing?->lst_barangay) }}" @disabled($fnLockedAttribute('barangay')) class="form-input">
            @error('barangay') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div data-hide-when="guide">
            <label for="establishment-lat" class="form-label">Latitude</label>
            <input id="establishment-lat" name="lat" type="number" step="any" min="-90" max="90" value="{{ old('lat', $listing?->lst_lat) }}" @disabled($fnLockedAttribute('lat')) class="form-input">
            @error('lat') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div data-hide-when="guide">
            <label for="establishment-lng" class="form-label">Longitude</label>
            <input id="establishment-lng" name="lng" type="number" step="any" min="-180" max="180" value="{{ old('lng', $listing?->lst_lng) }}" @disabled($fnLockedAttribute('lng')) class="form-input">
            @error('lng') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <x-dashboard.location-picker data-hide-when="guide" class="sm:col-span-2" latitude-input="establishment-lat" longitude-input="establishment-lng" :is-disabled="$fnLockedAttribute('lat')" />
    </div>
</section>

{{-- Summary: contact and operations --}}
<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Contact and operations</h2>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="establishment-contact-office" class="form-label">Contact person / office</label>
            <input id="establishment-contact-office" name="contact_office" type="text" maxlength="255" value="{{ old('contact_office', $listing?->lst_contact_office) }}" class="form-input">
            @error('contact_office') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-contact-phone" class="form-label">Contact number</label>
            <input id="establishment-contact-phone" name="contact_phone" type="tel" maxlength="255" value="{{ old('contact_phone', $listing?->lst_contact_phone) }}" class="form-input">
            @error('contact_phone') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-email" class="form-label">Email</label>
            <input id="establishment-email" name="email" type="email" maxlength="255" value="{{ old('email', $listing?->lst_email) }}" class="form-input">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="establishment-website" class="form-label">Website or social page</label>
            <input id="establishment-website" name="website" type="text" maxlength="255" value="{{ old('website', $listing?->lst_website) }}" class="form-input">
            @error('website') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="establishment-hours" class="form-label">Operating hours</label>
            <input id="establishment-hours" name="hours" type="text" maxlength="255" placeholder="e.g. 8:00 AM – 6:00 PM daily" value="{{ old('hours', $listing?->lst_hours) }}" class="form-input">
            @error('hours') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
