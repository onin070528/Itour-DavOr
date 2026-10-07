{{--
    Photos section of the Add Establishment form (R11). Files go through the
    existing photo workflow after the establishment is saved
    (EstablishmentImageUploader: same type/size/dimension checks, random stored
    names, LGU uploads reviewed by the PTO). Existing photos are managed on the
    Edit page with <x-dashboard.photo-manager>.
--}}
@php($intMaxPhotos = (int) config('establishment_images.max_live_images_per_listing'))

<section class="dashboard-panel" data-photo-upload-preview>
    <h2 class="dashboard-panel-title">Photos</h2>
    <p class="mt-1 text-xs text-sand-500">Optional. Up to {{ $intMaxPhotos }} JPG, PNG, or WebP photos, under 5 MB each. The Provincial Tourism Office approves photos before they appear publicly.</p>

    <div class="mt-4 flex flex-col gap-4">
        <div>
            <label for="establishment-photos" class="form-label">Choose photos</label>
            <input id="establishment-photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-photo-input class="form-input file:mr-3 file:rounded-sm file:border-0 file:bg-primary-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700">
            @error('photos') <p class="form-error">{{ $message }}</p> @enderror
            @error('photos.*') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        {{-- Filled by resources/js/establishment_form.js with a preview of each chosen file. --}}
        <ul data-photo-preview-list class="grid grid-cols-2 gap-3 empty:hidden sm:grid-cols-4"></ul>

        <div>
            <label for="establishment-photo-credit" class="form-label">Photo credit</label>
            <input id="establishment-photo-credit" name="credit" type="text" maxlength="255" placeholder="e.g. Mati City Tourism Office" value="{{ old('credit') }}" class="form-input">
            @error('credit') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-start gap-2 text-sm text-sand-700">
            <input type="checkbox" name="ownership_declared" value="1" @checked(old('ownership_declared')) class="mt-0.5 rounded-sm border-sand-300">
            <span>I confirm the LGU has permission to use these photos.</span>
        </label>
        @error('ownership_declared') <p class="form-error -mt-3">{{ $message }}</p> @enderror
    </div>
</section>
