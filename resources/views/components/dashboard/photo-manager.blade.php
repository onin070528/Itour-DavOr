{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Shared photo management grid — thumbnails, status, Add/Replace/Remove/Set Cover, drag-to-reorder. Used by all three portals.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@props(['listing', 'images', 'uploadRoute', 'replaceRouteName', 'removeRouteName', 'coverRouteName', 'creditRouteName', 'reorderRouteName', 'canUpload' => true, 'readOnly' => false])

@php
    $statusTone = fn ($status) => $status->badgeTone();
    $intLiveImageCount = $listing->liveImageCount();
    $intMaxLiveImages = $listing->maxLiveImages();
    $blnIsAtImageLimit = $listing->isAtOrOverImageLimit();
    $blnIsOverImageLimit = $listing->isOverImageLimit();
    // Soft suggestion only — plain text, never disables anything, never
    // shown on a public page. 3 is a fixed piece of copy here, not a
    // config-driven minimum (the enforced minimum is 1; see
    // establishment_images.min_live_images_per_listing).
    $blnShowMorePhotosSuggestion = $intLiveImageCount < 3;
@endphp

<div>
    <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-display text-base font-bold text-sand-900">Photos</h2>
                <p class="mt-0.5 text-xs text-sand-500">{{ $intLiveImageCount }} of {{ $intMaxLiveImages }} photos</p>
            </div>
            @if ($canUpload && ! $readOnly)
                @if ($blnIsAtImageLimit)
                    <button type="button" disabled title="You have reached {{ $intMaxLiveImages }} photos. Remove one to add another." class="inline-flex cursor-not-allowed items-center gap-2 rounded-sm bg-sand-200 px-3 py-2 text-sm font-semibold text-sand-500">
                        <i class="ti ti-plus" aria-hidden="true"></i>
                        Add Photo
                    </button>
                @else
                    <button type="button" data-modal-open="add-photo-modal" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-3 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        <i class="ti ti-plus" aria-hidden="true"></i>
                        Add Photo
                    </button>
                @endif
            @endif
        </div>

        @if ($readOnly)
            <p class="mt-2 text-xs text-sand-500">Photos can't be changed while this package is under review or live.</p>
        @elseif ($canUpload && $blnIsOverImageLimit)
            <p class="mt-2 text-xs text-danger">This listing has more than {{ $intMaxLiveImages }} photos. You cannot add more until some are removed.</p>
        @elseif ($canUpload && $blnIsAtImageLimit)
            <p class="mt-2 text-xs text-sand-600">You have reached {{ $intMaxLiveImages }} photos. Remove one to add another.</p>
        @endif

        @if ($blnShowMorePhotosSuggestion)
            <p class="mt-2 text-xs text-sand-500">Listings with 3 or more photos get more views.</p>
        @endif

        @if ($images->isEmpty())
            <x-dashboard.empty-state class="mt-4" icon="ti-camera" title="No photos yet" description="Add the first one above." />
        @else
            <div @unless ($readOnly) data-photo-reorder data-reorder-action="{{ route($reorderRouteName, $listing) }}" @endunless class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($images as $image)
                    <div data-photo-item data-image-id="{{ $image->img_id }}" @if (! $readOnly) draggable="true" @endif class="overflow-hidden rounded-md border border-sand-200 bg-sand-0 {{ ! $readOnly && $image->img_status->value === 'PUBLISHED' ? 'cursor-move' : '' }}">
                        <div class="relative aspect-[4/3] bg-sand-100">
                            <img src="{{ route('establishmentImages.file', [$image->img_id, 'thumbnail']) }}" alt="{{ $image->img_alt_text }}" class="h-full w-full object-cover">
                            @if ($image->img_is_cover)
                                <span class="absolute top-2 left-2 rounded-sm bg-primary-700 px-2 py-0.5 text-[10px] font-semibold text-sand-0">Cover</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <x-dashboard.status-badge :tone="$statusTone($image->img_status)">{{ $image->img_status->label() }}</x-dashboard.status-badge>
                            @if ($image->img_status->value === 'REJECTED' && $image->img_review_note)
                                <p class="mt-1.5 text-xs text-sand-600">{{ $image->img_review_note }}</p>
                            @endif
                            @if ($image->replacedBy)
                                <p class="mt-1.5 text-xs font-semibold text-warning">Replacement waiting for approval</p>
                            @endif
                            @if ($image->img_credit)
                                <p class="mt-1.5 text-xs text-sand-500">Credit: {{ $image->img_credit }}</p>
                            @endif

                            @if (! $readOnly && $image->img_status->value === 'PUBLISHED')
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @unless ($image->img_is_cover)
                                        <form method="POST" action="{{ route($coverRouteName, $image) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-sm border border-sand-300 px-2 py-1 text-[11px] font-semibold text-sand-700 hover:border-primary-300">Set as Cover</button>
                                        </form>
                                    @endunless
                                    @if ($canUpload)
                                        <button type="button" data-modal-open="replace-photo-{{ $image->img_id }}" class="rounded-sm border border-sand-300 px-2 py-1 text-[11px] font-semibold text-sand-700 hover:border-primary-300">Replace</button>
                                    @endif
                                </div>
                            @endif

                            @if (! $readOnly && in_array($image->img_status->value, ['PUBLISHED', 'PENDING', 'REJECTED'], true))
                                <form method="POST" action="{{ route($removeRouteName, $image) }}" class="mt-1.5" onsubmit="return confirm('Remove this photo?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-[11px] font-semibold text-danger hover:underline">Remove</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <x-dashboard.modal id="replace-photo-{{ $image->img_id }}" title="Replace Photo">
                        <form id="replace-photo-form-{{ $image->img_id }}" method="POST" action="{{ route($replaceRouteName, $image) }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                            @csrf
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-sand-700">New Photo <span class="text-danger" aria-hidden="true">*</span></label>
                                <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-sand-700">Credit (optional)</label>
                                <input name="credit" type="text" maxlength="255" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                            </div>
                        </form>
                        <x-slot:footer>
                            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                            <button type="submit" form="replace-photo-form-{{ $image->img_id }}" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Submit Replacement</button>
                        </x-slot:footer>
                    </x-dashboard.modal>
                @endforeach
            </div>
        @endif
    </div>

    <x-dashboard.modal id="add-photo-modal" title="Add Photo">
        <form id="add-photo-form" method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="flex flex-col gap-3">
            @csrf
            <input type="hidden" name="listing_id" value="{{ $listing->id }}">
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Photos <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Credit (optional)</label>
                <input name="credit" type="text" maxlength="255" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="ownership_declared" value="1" required class="mt-0.5 h-4 w-4 rounded border-sand-300 text-primary-700 focus:ring-primary-500">
                <span class="text-sm text-sand-800">I have permission to use this photo.</span>
            </label>
        </form>
        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="add-photo-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Upload</button>
        </x-slot:footer>
    </x-dashboard.modal>
</div>
