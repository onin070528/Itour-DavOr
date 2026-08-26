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

    @if ($profile)
        {{-- Images --}}
        <div id="image-gallery" class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-display text-base font-bold text-sand-900">Images</h2>
                    <p class="text-xs text-sand-500">Your featured photo is shown first on your public listing.</p>
                </div>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-photo-plus" aria-hidden="true"></i>
                    Add Image
                    <input id="image-upload-input" type="file" accept="image/*" class="hidden">
                </label>
            </div>

            <div data-image-grid class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($gallery as $image)
                    <div data-image-card class="group relative overflow-hidden rounded-md border border-sand-200">
                        <img src="{{ asset('storage/itour-images/'.$image['path']) }}" alt="{{ $image['caption'] }}" class="h-32 w-full object-cover">
                        <span data-primary-badge @class(['absolute top-2 left-2 rounded-sm bg-primary-700 px-2 py-0.5 text-[10px] font-semibold text-sand-0', 'hidden' => ! $image['primary']])>Featured</span>
                        <div class="absolute inset-0 flex items-end justify-between bg-gradient-to-t from-sand-900/60 via-transparent to-transparent p-2 opacity-0 transition-opacity group-hover:opacity-100">
                            <button type="button" data-set-primary @class(['rounded-sm bg-sand-0/90 px-2 py-1 text-[11px] font-semibold text-sand-800', 'hidden' => $image['primary']])>Set as Featured</button>
                            <button
                                type="button"
                                data-confirm-trigger
                                data-confirm-title="Remove this photo?"
                                data-confirm-message="This photo will be removed from your establishment's gallery."
                                data-confirm-label="Remove"
                                data-confirm-tone="danger"
                                data-confirm-success="Photo removed."
                                data-confirm-remove-target="[data-image-card]"
                                class="rounded-sm bg-danger px-2 py-1 text-[11px] font-semibold text-sand-0"
                            >Remove</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Profile form --}}
        <form class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Establishment Information</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div data-field class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Establishment Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="name" type="text" value="{{ $profile['name'] }}" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>

                <div data-field>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Category <span class="text-danger" aria-hidden="true">*</span></label>
                    <select name="category" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        @foreach ($categories as $c)
                            @if ($c['slug'] !== 'destinations')
                                <option value="{{ $c['slug'] }}" @selected($c['slug'] === $profile['category'])>{{ $c['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div data-field>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Municipality</label>
                    <input type="text" value="{{ $profile['municipality'] }}" disabled class="w-full rounded-sm border border-sand-200 bg-sand-100 px-3 py-2 text-sm text-sand-500">
                    <p class="mt-1 text-[11px] text-sand-500">Municipality changes go through your LGU tourism office.</p>
                </div>

                <div data-field class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Address / Barangay <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="address" type="text" value="{{ $profile['barangay'] }}" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>

                <div data-field class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">{{ $profile['description'] }}</textarea>
                </div>

                <div data-field>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Contact Number <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="phone" type="text" value="{{ $profile['contactPhone'] }}" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>

                <div data-field>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Operating Hours <span class="text-danger" aria-hidden="true">*</span></label>
                    <input name="hours" type="text" value="{{ $profile['hours'] }}" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>

                <div data-field>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Email</label>
                    <input name="email" type="email" placeholder="you@example.com" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>

                <div data-field>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Website / Social Media</label>
                    <input name="website" type="text" placeholder="Optional" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2 border-t border-sand-200 pt-5">
                <button type="button" data-toast-trigger data-toast-message="Establishment profile updated." class="rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    Save Changes
                </button>
            </div>
        </form>
    @else
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-building-store"
            title="No listing found for {{ $establishmentName }}"
            description="Your establishment profile hasn't been added to the tourism directory yet. Contact your LGU tourism office to get listed."
        />
    @endif
</x-layouts.dashboard>
