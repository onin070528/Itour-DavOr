<section class="relative h-screen min-h-screen w-full overflow-hidden flex flex-col" data-hero-carousel>
    @php
        // object-position tuned per photo after actually looking at each
        // one. Reuses existing itour-images assets only — no new/duplicate
        // images. hero-dahican-sunrise.jpg leads the carousel (always the
        // first/initially-visible slide) by request.
        $heroSlides = [
            ['file' => 'hero-dahican-sunrise.jpg', 'alt' => "Aerial view of Dahican Beach's coastline at sunrise, Davao Oriental", 'position' => '50% 50%'],
            ['file' => 'slide1.jpg', 'alt' => 'A white sandbar and turquoise reef off the Davao Oriental coast', 'position' => '50% 55%'],
            ['file' => 'slide3.jpg', 'alt' => 'A marine skeleton exhibit at a Davao Oriental museum', 'position' => '50% 55%'],
            ['file' => 'slide4.jpg', 'alt' => 'Aerial view of a forested island ringed by white sand and reef, Davao Oriental', 'position' => '50% 50%'],
            ['file' => 'slide5.jpg', 'alt' => 'A spiral staircase viewpoint overlooking the sea, Davao Oriental', 'position' => '60% 40%'],
        ];
    @endphp

    {{-- Background carousel: stacked, absolutely-positioned slides
         cross-fading via opacity only (transition-opacity duration-1000).
         The first slide starts visible (opacity-100) so there's no flash
         before resources/js/app.js's initHeroCarousel() runs. z-0 keeps
         every slide behind the gradient overlay and the centered content
         below. --}}
    @foreach ($heroSlides as $index => $slide)
        <img
            data-hero-slide="{{ $index }}"
            src="{{ asset('storage/itour-images/'.$slide['file']) }}"
            alt="{{ $slide['alt'] }}"
            style="object-position: {{ $slide['position'] }}"
            @class(['absolute inset-0 z-0 h-full w-full object-cover transition-opacity duration-1000', 'opacity-100' => $index === 0, 'opacity-0' => $index !== 0])
        >
    @endforeach

    {{-- Darkens the left (where the headline/search/pills sit) and fades to
         transparent on the right so the destination photo stays bright and
         visible. Lighter than before and tinted with this app's own
         primary-900 teal (rather than flat black) — a plain black overlay
         read as somber/moody for a tourism page; this keeps the white text
         legible while the photo itself stays the main, inviting visual.
         Applied only to this background layer — never to the section, the
         content wrapper, or any text below. --}}
    <div class="absolute inset-0 z-10 bg-gradient-to-r from-primary-900/65 via-primary-900/25 to-transparent"></div>

    {{-- The topbar renders in the shared public layout (components/layouts/
         public.blade.php), sticky above this hero, so it stays in view as
         the page scrolls or the in-page Nearby/Reviews/About links are
         used — it's no longer embedded in this section. --}}

    {{-- The photo bleeds full-width edge to edge; only this inner content
         column is constrained. flex + my-auto on the section/this wrapper
         centers it vertically within the hero's full h-screen height. --}}
    <div class="relative z-20 container mx-auto max-w-4xl px-6 my-auto">
        <h1 class="font-['Outfit'] text-4xl font-extrabold tracking-tight text-sand-0 drop-shadow-md sm:text-5xl lg:text-6xl">
            Where the Philippines<br>greets the sunrise first.
        </h1>
        {{-- sand-100 (this app's near-white neutral) stands in for the
             spec's literal text-slate-100 — solid, no transparency. --}}
        <p class="mt-4 max-w-lg text-base leading-relaxed text-sand-100 drop-shadow-sm sm:text-lg">
            Explore beaches, waterfalls and a UNESCO World Heritage mountain range across the 11 municipalities of Davao Oriental.
        </p>

        {{-- Fully solid white card (sand-0 is this app's #ffffff token) —
             no transparency — so it reads clearly over any slide. --}}
        <form action="{{ route('explore') }}" method="GET" class="mt-8 flex max-w-xl items-center gap-2 rounded-md bg-sand-0 p-2 shadow-2xl">
            <i class="ti ti-search ml-2 text-sand-500" aria-hidden="true"></i>
            <label for="hero-search" class="sr-only">Search a destination, accommodation, restaurant, or service</label>
            <input
                id="hero-search"
                type="search"
                name="q"
                placeholder="Search a destination, resort, restaurant or service..."
                class="w-full flex-1 border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-400 focus:outline-none"
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
                <a href="{{ $pill['href'] }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-white/30 bg-primary-900/35 px-3.5 py-2 text-xs font-semibold text-sand-0 backdrop-blur-sm transition-colors hover:bg-primary-900/55">
                    <i class="ti {{ $pill['icon'] }}" aria-hidden="true"></i>
                    {{ $pill['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Pagination dots, now flowing at the bottom of this search-bar
             block instead of pinned to the viewport edge. --}}
        <div class="mt-6 flex items-center justify-center gap-1.5" role="tablist" aria-label="Hero background slides">
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
    </div>
</section>
