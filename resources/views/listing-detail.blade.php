<x-layouts.public :title="$listing->name">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('explore') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-900">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back to Explore
        </a>

        <div class="relative mt-4 h-64 overflow-hidden rounded-md bg-sand-200 sm:h-96">
            @if ($coverImageUrl)
                <img src="{{ $coverImageUrl }}" alt="{{ $listing->name }}" class="absolute inset-0 h-full w-full object-cover">
            @else
                <div class="absolute inset-0 flex items-center justify-center bg-sand-200">
                    <i class="ti {{ $categoryIcon }} text-6xl text-sand-400" aria-hidden="true"></i>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/60 via-transparent to-transparent"></div>
            <span class="absolute bottom-4 left-5 rounded-sm bg-sand-900/45 px-3 py-1.5 text-xs font-semibold tracking-wide text-sand-0 uppercase">{{ $categoryLabel }}</span>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold text-sand-900 sm:text-3xl">{{ $listing->name }}</h1>
                <p class="mt-1 flex items-center gap-1 text-sm font-medium text-sand-500">
                    <i class="ti ti-map-pin" aria-hidden="true"></i>
                    {{ $listing->barangay }}, {{ $listing->municipality }}
                </p>
            </div>
            @if ($listing->rating !== null)
                <span class="inline-flex items-center gap-1 text-sm font-semibold text-sand-800">
                    <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                    {{ number_format((float) $listing->rating, 1) }}
                </span>
            @endif
        </div>

        @if ($listing->description)
            <p class="mt-4 max-w-3xl text-sm leading-relaxed text-sand-700">{{ $listing->description }}</p>
        @endif

        @if (! empty($listing->tags))
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($listing->tags as $tag)
                    <span class="rounded-sm bg-sand-100 px-2 py-1 text-xs font-medium text-sand-700">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 text-sm sm:grid-cols-2">
            @foreach ([
                'contact_office' => ['icon' => 'ti-building', 'label' => 'Contact office'],
                'contact_phone' => ['icon' => 'ti-phone', 'label' => 'Phone'],
                'hours' => ['icon' => 'ti-clock', 'label' => 'Hours'],
                'email' => ['icon' => 'ti-mail', 'label' => 'Email'],
                'website' => ['icon' => 'ti-world', 'label' => 'Website'],
            ] as $field => $meta)
                @if ($listing->{$field})
                    <div class="flex items-start gap-2.5">
                        <i class="ti {{ $meta['icon'] }} mt-0.5 text-primary-700" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">{{ $meta['label'] }}</dt>
                            <dd class="break-words text-sand-800">{{ $listing->{$field} }}</dd>
                        </div>
                    </div>
                @endif
            @endforeach
        </dl>

        {{-- Simple gallery of every PUBLISHED photo (7E). Each one shows its
             credit line when set, and falls back to the establishment's
             name for alt text. Hidden entirely when there's nothing beyond
             the cover photo already shown above. --}}
        @if ($galleryImages->isNotEmpty())
            <div class="mt-8 border-t border-sand-200 pt-6">
                <h2 class="font-display text-lg font-bold text-sand-900">Photos</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($galleryImages as $image)
                        <figure class="overflow-hidden rounded-md bg-sand-200">
                            <a href="{{ route('establishmentImages.file', [$image, 'full']) }}" target="_blank" rel="noopener">
                                <img
                                    src="{{ route('establishmentImages.file', [$image, 'thumbnail']) }}"
                                    alt="{{ $image->img_alt_text ?: $listing->name }}"
                                    loading="lazy"
                                    class="h-32 w-full object-cover transition-transform hover:scale-105 sm:h-36"
                                >
                            </a>
                            @if ($image->img_credit)
                                <figcaption class="px-1.5 py-1 text-[11px] text-sand-500">{{ $image->img_credit }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.public>
