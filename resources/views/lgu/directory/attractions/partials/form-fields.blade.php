{{--
    Tourist attraction (destination-only) fields shared by the Add and Edit pages (R9; Objective 3:
    destination type, visitor information, and entrance fee).
    Expects: $listing (null on Add), $blnIsContentLocked (public content locked while a request is
    with the PTO), optional $blnIsLive (edits to public content are held for PTO review) and
    $strReviewRemarks. No municipality, account, QR, or reporting field: the municipality comes
    from the LGU's own account and an attraction has none of the others.
--}}
@php
    $blnIsLive ??= false;
    $strReviewRemarks ??= null;
    // Form field names are the column names without the lst_ prefix.
    $fnIsLocked = fn (string $strField) => $blnIsContentLocked && in_array('lst_'.$strField, \App\Models\Listing::PUBLIC_CONTENT_FIELDS, true);
    $strSelectedType = old('type', $listing?->lst_type);
@endphp

@if ($blnIsContentLocked)
    <p class="flex items-start gap-2 rounded-sm border border-sand-200 bg-sand-50 px-3 py-2.5 text-xs text-sand-600">
        <i class="ti ti-lock mt-0.5" aria-hidden="true"></i>
        <span>Name, type, location, description, visitor information, and entrance fee are part of the listing the PTO is reviewing, so they can't be changed until it decides. Contact details and visiting hours can still be updated.</span>
    </p>
@elseif ($blnIsLive)
    <div class="flex flex-col gap-2 rounded-sm border border-primary-300 bg-primary-100 px-3 py-2.5 text-xs text-primary-900">
        <p class="flex items-start gap-2">
            <i class="ti ti-world mt-0.5" aria-hidden="true"></i>
            <span>This attraction is published. Changes to its name, type, location, description, visitor information, or entrance fee are sent to the PTO for review, and the published version stays on the public site until they are approved. Contact details and visiting hours save straight away.</span>
        </p>
        @if ($strReviewRemarks)
            <p class="rounded-sm border border-warning/30 bg-warning-bg px-2.5 py-2 text-warning"><span class="font-semibold">PTO remarks:</span> {{ $strReviewRemarks }}</p>
        @endif
    </div>
@endif

{{-- Summary: about the attraction --}}
<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">About the attraction</h2>

    <div class="mt-4 grid grid-cols-1 gap-4">
        <div>
            <label for="attraction-name" class="form-label">Attraction name <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="attraction-name" name="name" type="text" maxlength="255" required placeholder="e.g. Aliwagwag Falls" value="{{ old('name', $listing?->lst_name) }}" @disabled($fnIsLocked('name')) class="form-input">
            @error('name') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-type" class="form-label">Destination type</label>
            <select id="attraction-type" name="type" @disabled($fnIsLocked('type')) class="form-input">
                <option value="">Not set</option>
                @foreach (config('tourism_directory.destination_types') as $strType)
                    <option value="{{ $strType }}" @selected($strSelectedType === $strType)>{{ $strType }}</option>
                @endforeach
            </select>
            @error('type') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-accreditation" class="form-label">Accreditation status</label>
            <input id="attraction-accreditation" name="accreditation_status" type="text" maxlength="255" placeholder="e.g. DOT Accredited" value="{{ old('accreditation_status', $listing?->lst_accreditation_status) }}" class="form-input">
            <p class="form-hint">Enter "DOT Accredited" only if the Department of Tourism has accredited this attraction. The public listing then shows a DOT Accredited badge.</p>
            @error('accreditation_status') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-description" class="form-label">Description</label>
            <textarea id="attraction-description" name="description" rows="5" maxlength="5000" placeholder="What visitors can see and do here." @disabled($fnIsLocked('description')) class="form-input">{{ old('description', $listing?->lst_description) }}</textarea>
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
            <label for="attraction-barangay" class="form-label">Barangay / Address <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="attraction-barangay" name="barangay" type="text" maxlength="255" required value="{{ old('barangay', $listing?->lst_barangay) }}" @disabled($fnIsLocked('barangay')) class="form-input">
            @error('barangay') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-lat" class="form-label">Latitude</label>
            <input id="attraction-lat" name="lat" type="number" step="any" min="-90" max="90" value="{{ old('lat', $listing?->lst_lat) }}" @disabled($fnIsLocked('lat')) class="form-input">
            @error('lat') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-lng" class="form-label">Longitude</label>
            <input id="attraction-lng" name="lng" type="number" step="any" min="-180" max="180" value="{{ old('lng', $listing?->lst_lng) }}" @disabled($fnIsLocked('lng')) class="form-input">
            @error('lng') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <x-dashboard.location-picker class="sm:col-span-2" latitude-input="attraction-lat" longitude-input="attraction-lng" :is-disabled="$fnIsLocked('lat')" />
    </div>
</section>

{{-- Summary: visitor information (reviewed by the PTO, like the description) --}}
<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Visitor information</h2>

    <div class="mt-4 grid grid-cols-1 gap-4">
        <div>
            <label for="attraction-visitor-information" class="form-label">What visitors should know</label>
            <textarea id="attraction-visitor-information" name="visitor_information" rows="4" maxlength="5000" placeholder="Permits, what to bring, safety reminders, best time to visit." @disabled($fnIsLocked('visitor_information')) class="form-input">{{ old('visitor_information', $listing?->lst_visitor_information) }}</textarea>
            @error('visitor_information') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-entrance-fee" class="form-label">Entrance fee</label>
            <input id="attraction-entrance-fee" name="entrance_fee" type="text" maxlength="255" placeholder="e.g. PHP 50 adults, PHP 20 children; free for residents" value="{{ old('entrance_fee', $listing?->lst_entrance_fee) }}" @disabled($fnIsLocked('entrance_fee')) class="form-input">
            @error('entrance_fee') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- Summary: contact details and visiting hours (not reviewed by the PTO) --}}
<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Contact details and visiting hours</h2>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="attraction-contact-office" class="form-label">Managing office</label>
            <input id="attraction-contact-office" name="contact_office" type="text" maxlength="255" placeholder="e.g. Cateel Municipal Tourism Office" value="{{ old('contact_office', $listing?->lst_contact_office) }}" class="form-input">
            <p class="form-hint">The office that manages this attraction and answers visitor inquiries.</p>
            @error('contact_office') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-contact-phone" class="form-label">Contact number</label>
            <input id="attraction-contact-phone" name="contact_phone" type="text" maxlength="255" value="{{ old('contact_phone', $listing?->lst_contact_phone) }}" class="form-input">
            @error('contact_phone') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-hours" class="form-label">Visiting hours</label>
            <input id="attraction-hours" name="hours" type="text" maxlength="255" placeholder="e.g. 7:00 AM – 5:00 PM daily" value="{{ old('hours', $listing?->lst_hours) }}" class="form-input">
            @error('hours') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="attraction-website" class="form-label">Website or social page</label>
            <input id="attraction-website" name="website" type="text" maxlength="255" value="{{ old('website', $listing?->lst_website) }}" class="form-input">
            @error('website') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
