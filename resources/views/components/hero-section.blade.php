<section class="relative overflow-hidden" data-hero-carousel>
    @php
        // object-position tuned per photo after actually looking at each
        // one — they're wildly different aspect ratios/compositions, so a
        // single shared crop point would cut off the actual subject in at
        // least two of these (Hamiguitan's trees sit left-and-low against a
        // mostly-empty sky; Pujada Bay's viewing deck eats the bottom
        // third). Reuses existing itour-images assets only — no new/
        // duplicate images.
        $heroSlides = [
            ['file' => 'hero-dahican-sunrise.jpg', 'alt' => "Aerial view of Dahican Beach's coastline at sunrise, Davao Oriental", 'caption' => '📍 Dahican Beach, Mati City', 'position' => '50% 50%'],
            ['file' => 'Aliwagwag-Falls-6.jpg', 'alt' => 'Aliwagwag Falls cascading through the rainforest, Cateel', 'caption' => '📍 Aliwagwag Falls, Cateel', 'position' => '50% 20%'],
            ['file' => 'hamiguitan.jpg', 'alt' => 'Mount Hamiguitan Range Wildlife Sanctuary, a UNESCO World Heritage Site in Governor Generoso', 'caption' => '📍 Mount Hamiguitan, Governor Generoso', 'position' => '25% 65%'],
            ['file' => 'pujada-bay.jpg', 'alt' => "Pujada Bay's mountains and turquoise water, Mati City", 'caption' => '📍 Pujada Bay, Mati City', 'position' => '50% 25%'],
            ['file' => 'Cape-San-Agustin.jpg', 'alt' => "Cape San Agustin's coastline and lighthouse point, Governor Generoso", 'caption' => '📍 Cape San Agustin, Governor Generoso', 'position' => '50% 35%'],
        ];
    @endphp

    {{-- Background carousel: stacked, absolutely-positioned slides
         cross-fading via opacity only (transition-opacity duration-1000),
         so nothing in the foreground column below ever re-renders or
         shifts. The first slide starts visible (opacity-100) so there's no
         flash before resources/js/app.js's initHeroCarousel() runs. --}}
    @foreach ($heroSlides as $index => $slide)
        <img
            data-hero-slide="{{ $index }}"
            data-hero-caption="{{ $slide['caption'] }}"
            src="{{ asset('storage/itour-images/'.$slide['file']) }}"
            alt="{{ $slide['alt'] }}"
            style="object-position: {{ $slide['position'] }}"
            @class(['absolute inset-0 h-full w-full object-cover transition-opacity duration-1000', 'opacity-100' => $index === 0, 'opacity-0' => $index !== 0])
        >
    @endforeach

    {{-- Darkens the left (where the headline/search/CTA sit) and fades to
         nearly clear on the right so the destination photo stays visible;
         a light top/bottom vignette keeps the badge, caption, and
         indicators readable against any of the five images without
         darkening the hero as a whole. --}}
    <div class="absolute inset-0 bg-gradient-to-r from-primary-900/85 via-primary-900/45 to-primary-900/5"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary-900/35 via-transparent to-primary-900/30"></div>

    {{-- Location caption (bottom-left) — a fixed-height wrapper keeps the
         changing text from ever shifting layout, matching the pill styling
         already used for the badge above the headline. --}}
    <p class="absolute bottom-5 left-4 inline-flex h-7 items-center rounded-full border border-white/30 bg-white/10 px-3.5 text-xs font-semibold text-sand-0 backdrop-blur-sm sm:left-6 lg:left-8">
        <span data-hero-caption-text>📍 Dahican Beach, Mati City</span>
    </p>

    {{-- Slide indicators (bottom-center) --}}
    <div class="absolute bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-1.5" role="tablist" aria-label="Hero background slides">
        @foreach ($heroSlides as $i => $slide)
            <button
                type="button"
                data-hero-indicator="{{ $i }}"
                role="tab"
                aria-label="Show slide {{ $i + 1 }}"
                aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                @class(['h-2 rounded-full transition-all duration-300', 'w-6 bg-white' => $i === 0, 'w-2 bg-white/40' => $i !== 0])
            ></button>
        @endforeach
    </div>

    {{-- The photo bleeds full-width edge to edge; only this inner content
         column is constrained, so the headline/search/pills line up with
         the nav logo and the sections below rather than the image. --}}
    <div class="relative mx-auto max-w-[1200px] px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
        <span class="inline-flex items-center gap-2 text-xs font-bold tracking-widest text-sand-0/90 uppercase">
            <i class="ti ti-wind" aria-hidden="true"></i>
            Official Tourism Platform · Province of Davao Oriental
        </span>

        <h1 class="mt-4 max-w-xl text-4xl font-extrabold tracking-tight text-sand-0 sm:text-5xl lg:text-6xl">
            Where the Philippines greets the sunrise first.
        </h1>
        <p class="mt-4 max-w-lg text-base leading-relaxed text-white/85 sm:text-lg">
            Explore beaches, waterfalls and a UNESCO World Heritage mountain range across the 11 municipalities of Davao Oriental.
        </p>

        <form action="{{ route('explore') }}" method="GET" class="mt-8 flex max-w-xl items-center gap-2 rounded-md bg-sand-0 p-2 shadow-md">
            <i class="ti ti-search ml-2 text-sand-500" aria-hidden="true"></i>
            <label for="hero-search" class="sr-only">Search a destination, accommodation, restaurant, or service</label>
            <input
                id="hero-search"
                type="search"
                name="q"
                placeholder="Search a destination, resort, restaurant or service..."
                class="w-full flex-1 border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none"
            >
            <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-sm bg-accent-500 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-accent-600">
                Explore now
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        {{-- flex-nowrap + overflow-x-auto keeps every pill on one row at any
             width, scrolling horizontally instead of wrapping to a second
             line (scrollbar-hide utility: resources/css/app.css). --}}
        <div class="scrollbar-hide mt-4 flex flex-nowrap gap-2 overflow-x-auto pb-1">
            @foreach ([
                ['icon' => 'ti-map-pin', 'label' => 'Destinations', 'href' => route('explore', ['category' => 'destinations'])],
                ['icon' => 'ti-bed', 'label' => 'Accommodation', 'href' => route('explore', ['category' => 'accommodation'])],
                ['icon' => 'ti-tools-kitchen-2', 'label' => 'Restaurants', 'href' => route('explore', ['category' => 'restaurants'])],
                ['icon' => 'ti-bus', 'label' => 'Transportation', 'href' => route('explore', ['category' => 'transportation'])],
                ['icon' => 'ti-first-aid-kit', 'label' => 'Emergency', 'href' => '#footer-emergency'],
            ] as $pill)
                <a href="{{ $pill['href'] }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-white/30 bg-white/5 px-3.5 py-2 text-xs font-semibold text-sand-0 backdrop-blur-sm transition-colors hover:bg-white/15">
                    <i class="ti {{ $pill['icon'] }}" aria-hidden="true"></i>
                    {{ $pill['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</section>
