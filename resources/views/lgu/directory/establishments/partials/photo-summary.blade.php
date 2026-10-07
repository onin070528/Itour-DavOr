{{-- Read-only photo strip on an establishment or attraction details page; photos are managed in the Photos section of its Edit page. Expects $listing (establishmentImages loaded). --}}
@php($colImages = $listing->establishmentImages->reject(fn ($objImage) => $objImage->img_status === \App\Enums\ImageStatus::Archived))

<section class="dashboard-panel">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="dashboard-panel-title">Photos</h2>
            <p class="mt-0.5 text-xs text-sand-500">{{ $listing->liveImageCount() }} of {{ $listing->maxLiveImages() }} photos</p>
        </div>
        <a href="{{ $listing->isDestinationOnly() ? route('lgu.directory.attractions.edit', $listing) : route('lgu.directory.establishments.edit', $listing) }}#photos" class="btn-small">
            <i class="ti ti-camera" aria-hidden="true"></i>
            Manage photos
        </a>
    </div>

    @if ($colImages->isEmpty())
        <x-dashboard.empty-state class="mt-4" icon="ti-camera" title="No photos yet" description="Add photos from the Edit page." />
    @else
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($colImages as $objImage)
                <li class="overflow-hidden rounded-sm border border-sand-200 bg-sand-50">
                    <img src="{{ route('establishmentImages.file', [$objImage->img_id, 'thumbnail']) }}" alt="{{ $objImage->img_alt_text ?? '' }}" loading="lazy" class="h-24 w-full object-cover">
                    <p class="flex items-center justify-between gap-1 px-2 py-1 text-xs text-sand-600">
                        <span>{{ $objImage->img_status->label() }}</span>
                        @if ($objImage->img_is_cover)
                            <span class="font-semibold text-primary-700">Cover</span>
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
