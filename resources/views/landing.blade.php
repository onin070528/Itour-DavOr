<x-layouts.public>
    <x-hero-section />

    {{-- Signature Experiences: a curated mix of destinations and
         establishments in one showcase (see
         TourismCatalog::signatureExperiences()) — replaces the previous
         separate "Featured Destinations" and "Tourism Establishments
         Preview" sections. --}}
    <section id="destinations" class="border-y border-sand-200 bg-sand-100">
        <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <x-section-heading
                eyebrow="Featured Destinations"
                description="Curated places that capture the nature, culture and spirit of the Philippine sunrise capital."
                action-label="Explore all"
                :action-href="route('explore')"
            >
                Signature experiences of Davao Oriental
            </x-section-heading>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($signatureExperiences as $listing)
                    <x-experience-card :listing="$listing" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Explore by Municipality --}}
    <section class="bg-sand-100">
        <div class="mx-auto max-w-[1200px] px-4 py-14 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <x-section-heading
                    eyebrow="Explore by Municipality"
                    description="Find local destinations and accredited services across every city and municipality."
                >
                    Eleven places, one remarkable coast
                </x-section-heading>

                <div class="flex flex-wrap gap-2.5 lg:max-w-lg lg:justify-end">
                    @foreach ($municipalities as $municipality)
                        <a
                            href="{{ route('explore', ['municipality' => $municipality['name']]) }}"
                            class="inline-flex items-center gap-1.5 rounded-full border border-sand-300 bg-sand-0 px-3.5 py-2 text-sm font-medium text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                        >
                            <i class="ti ti-map-pin" aria-hidden="true"></i>
                            {{ $municipality['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Trusted Tourism Establishments --}}
    <section>
        <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <x-section-heading
                eyebrow="Stay, Dine and Travel"
                description="Verified places for every part of your visit."
                action-label="Open directory"
                :action-href="route('explore')"
            >
                Trusted tourism establishments
            </x-section-heading>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featuredEstablishments as $listing)
                    <x-establishment-preview-card :listing="$listing" />
                @endforeach
            </div>
        </div>
    </section>

    <x-near-you-section :places="$nearbyPlaces" />

    {{-- Tourist Experience / Reviews --}}
    <section id="reviews" class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <x-section-heading
            eyebrow="Tourist Reviews"
            description="Authentic multilingual feedback helps future travelers and improves tourism services."
            action-label="Read all reviews"
            action-href="#"
        >
            Stories from the road
        </x-section-heading>

        <div class="mt-10 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (array_slice($reviews, 0, 3) as $review)
                <x-review-card :review="$review" />
            @endforeach
        </div>
    </section>

    {{-- Behind iTOUR --}}
    <section id="about" class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8">
        <div class="flex h-11 w-11 items-center justify-center rounded-md bg-primary-100">
            <i class="ti ti-chart-bar text-xl text-primary-700" aria-hidden="true"></i>
        </div>

        <div class="mt-4">
            <x-section-heading
                eyebrow="Behind iTOUR"
                description="Verified reports and optional QR arrival collection flow into one provincial view, helping tourism offices act on better information."
            >
                Tourism insight that serves the whole province
            </x-section-heading>
        </div>

        <div class="mt-10 grid grid-cols-1 gap-10 sm:grid-cols-2">
            <div>
                <p class="flex items-center gap-2 text-sm font-bold text-sand-900">
                    <i class="ti ti-chart-bar text-primary-700" aria-hidden="true"></i>
                    Main reporting workflow
                </p>
                <ol class="mt-4 flex flex-col gap-3">
                    @foreach ([
                        'Establishment submits tourist arrival report',
                        'LGU tourism office reviews and validates',
                        'iTOUR centralizes provincial data',
                        'PTO monitors trends and reports',
                    ] as $i => $step)
                        <li class="flex items-center gap-3 text-sm text-sand-700">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-700 text-xs font-bold text-sand-0">{{ $i + 1 }}</span>
                            {{ $step }}
                        </li>
                    @endforeach
                </ol>
            </div>

            <div>
                <p class="flex items-center gap-2 text-sm font-bold text-sand-900">
                    <i class="ti ti-qrcode text-accent-600" aria-hidden="true"></i>
                    Optional QR arrival collection
                </p>
                <ol class="mt-4 flex flex-col gap-3">
                    @foreach ([
                        'Establishment displays its own QR code',
                        'Tourist opens and completes the arrival form',
                        'Record enters the same central system',
                        'Existing LGU reporting continues',
                    ] as $i => $step)
                        <li class="flex items-center gap-3 text-sm text-sand-700">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-sand-0">{{ $i + 1 }}</span>
                            {{ $step }}
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <x-cta-section />
</x-layouts.public>
