<x-layouts.public>
    <x-hero-section />

    {{-- Quick Exploration --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <x-section-heading eyebrow="Get Started">
            Explore by Category
        </x-section-heading>

        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($categories as $category)
                <x-category-card
                    :icon="$category['icon']"
                    :label="$category['label']"
                    :description="$category['description']"
                    :href="$category['href']"
                    :tone="$category['tone']"
                />
            @endforeach
        </div>
    </section>

    {{-- Featured Destinations --}}
    <section id="destinations" class="border-y border-sand-200 bg-sand-100">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <x-section-heading
                eyebrow="Featured Destinations"
                description="Signature experiences of Davao Oriental — hand-picked destinations from across the province's 11 municipalities."
            >
                Where to go first
            </x-section-heading>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($destinations as $destination)
                    <x-featured-destination-card :destination="$destination" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Tourism Directory Preview --}}
    <section id="directory" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <x-section-heading
            eyebrow="Tourism Directory"
            description="Browse accredited hotels, resorts, restaurants, travel services, and activity providers across the province."
        >
            Explore Tourism Establishments
        </x-section-heading>

        <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($establishments as $establishment)
                <x-establishment-card :establishment="$establishment" />
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="#" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-6 py-3 text-sm font-semibold text-sand-800 shadow-sm transition-colors hover:border-primary-300 hover:text-primary-700">
                View Tourism Directory
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <x-near-you-section />

    {{-- Tourist Experience / Reviews --}}
    <section id="reviews" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <x-section-heading
            eyebrow="Tourist Experience"
            description="Real impressions shared by visitors exploring Davao Oriental's destinations and establishments."
        >
            What Visitors Are Saying
        </x-section-heading>

        <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($reviews as $review)
                <x-review-card :review="$review" />
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="#" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-6 py-3 text-sm font-semibold text-sand-800 shadow-sm transition-colors hover:border-primary-300 hover:text-primary-700">
                View All Reviews
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <x-cta-section />
</x-layouts.public>
