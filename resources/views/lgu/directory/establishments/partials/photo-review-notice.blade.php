{{-- Photos uploaded by establishment accounts that wait for this LGU's approval (existing photo workflow, unchanged). --}}
<div class="mt-6 flex flex-col gap-3 rounded-md border border-warning/30 bg-warning-bg px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="flex items-center gap-2 text-sm text-sand-800">
        <i class="ti ti-camera text-warning" aria-hidden="true"></i>
        <span><strong>Photos to review:</strong> {{ $intPhotoReviewCount }} {{ Str::plural('establishment', $intPhotoReviewCount) }} {{ $intPhotoReviewCount === 1 ? 'has' : 'have' }} new photos waiting for your approval.</span>
    </p>
    <a href="{{ route('lgu.images.index', ['tab' => 'approval']) }}" class="btn-small shrink-0 bg-sand-0">
        Review photos
        <i class="ti ti-arrow-right" aria-hidden="true"></i>
    </a>
</div>
