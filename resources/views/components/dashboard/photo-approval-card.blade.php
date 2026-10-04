{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : One establishment's photo-approval card — thumbnails, Approve all, Return all, and per-photo Return this one. Used by the LGU and PTO queues.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@props(['card', 'approveAllRouteName', 'returnBatchRouteName'])

@php
    $listing = $card['listing'];
    $images = $card['images'];
    $allImageIds = $images->pluck('img_id')->all();
@endphp

<div class="rounded-md border border-sand-200 bg-sand-0 p-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="font-display text-base font-bold text-sand-900">{{ $listing->name }}</p>
            <p class="text-xs text-sand-500">{{ $listing->municipality }}</p>
            <p class="mt-1 text-xs text-sand-500">
                Uploaded by {{ $card['uploader']->name ?? '—' }} · Latest {{ $card['latestUploadAt']->format('M j, Y') }}
                · {{ $images->count() }} {{ Str::plural('photo', $images->count()) }} waiting
            </p>
        </div>

        <div class="flex shrink-0 gap-2">
            <form method="POST" action="{{ route($approveAllRouteName, $listing) }}">
                @csrf
                @method('PATCH')
                @foreach ($allImageIds as $imageId)
                    <input type="hidden" name="image_ids[]" value="{{ $imageId }}">
                @endforeach
                <button type="submit" class="rounded-sm bg-primary-700 px-3 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    Approve all
                </button>
            </form>
            <button type="button" data-modal-open="return-all-{{ $listing->id }}" class="rounded-sm border border-danger/30 px-3 py-2.5 text-sm font-semibold text-danger hover:bg-danger-bg">
                Return all
            </button>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($images as $image)
            <div class="overflow-hidden rounded-md border border-sand-200">
                <button type="button" data-modal-open="enlarge-photo-{{ $image->img_id }}" class="block w-full cursor-zoom-in">
                    <img src="{{ route('establishmentImages.file', [$image->img_id, 'thumbnail']) }}" alt="{{ $image->img_alt_text }}" class="h-32 w-full object-cover">
                </button>

                @if ($image->replaces)
                    <p class="bg-sand-50 px-2 py-1 text-[10px] font-semibold text-sand-600">Replaces this photo</p>
                    <button type="button" data-modal-open="enlarge-photo-{{ $image->replaces->img_id }}" class="block w-full cursor-zoom-in border-t border-sand-100">
                        <img src="{{ route('establishmentImages.file', [$image->replaces->img_id, 'thumbnail']) }}" alt="{{ $image->replaces->img_alt_text }}" class="h-16 w-full object-cover opacity-80">
                    </button>
                @endif

                <div class="flex items-center justify-between gap-1 p-2">
                    <span class="text-[11px] text-sand-500">{{ $image->img_created_at->format('M j') }}</span>
                    <button type="button" data-modal-open="return-one-{{ $image->img_id }}" class="text-[11px] font-semibold text-danger hover:underline">
                        Return this one
                    </button>
                </div>
            </div>

            <x-dashboard.modal id="enlarge-photo-{{ $image->img_id }}" title="Photo" max-width="max-w-2xl">
                <img src="{{ route('establishmentImages.file', [$image->img_id, 'full']) }}" alt="{{ $image->img_alt_text }}" class="w-full rounded-md">
            </x-dashboard.modal>

            <x-dashboard.modal id="return-one-{{ $image->img_id }}" title="Return This Photo">
                <form id="return-one-form-{{ $image->img_id }}" method="POST" action="{{ route($returnBatchRouteName, $listing) }}" class="flex flex-col gap-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="image_ids[]" value="{{ $image->img_id }}">
                    <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea name="reason" rows="3" required placeholder="Why is this photo being returned?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                </form>
                <x-slot:footer>
                    <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                    <button type="submit" form="return-one-form-{{ $image->img_id }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return Photo</button>
                </x-slot:footer>
            </x-dashboard.modal>
        @endforeach
    </div>

    <x-dashboard.modal id="return-all-{{ $listing->id }}" title="Return All Photos">
        <form id="return-all-form-{{ $listing->id }}" method="POST" action="{{ route($returnBatchRouteName, $listing) }}" class="flex flex-col gap-3">
            @csrf
            @method('PATCH')
            @foreach ($allImageIds as $imageId)
                <input type="hidden" name="image_ids[]" value="{{ $imageId }}">
            @endforeach
            <p class="text-sm text-sand-700">This returns all {{ count($allImageIds) }} {{ Str::plural('photo', count($allImageIds)) }} waiting on this card, with the same reason.</p>
            <label class="text-xs font-semibold text-sand-700">Reason <span class="text-danger" aria-hidden="true">*</span></label>
            <textarea name="reason" rows="3" required placeholder="Why are these photos being returned?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
        </form>
        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="return-all-form-{{ $listing->id }}" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return All</button>
        </x-slot:footer>
    </x-dashboard.modal>
</div>
