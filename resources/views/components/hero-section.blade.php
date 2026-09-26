<section class="relative overflow-hidden">
    <img
        src="{{ asset('storage/itour-images/hero-dahican-sunrise.jpg') }}"
        alt="Aerial view of Dahican Beach's coastline at sunrise, Davao Oriental"
        class="absolute inset-0 h-full w-full object-cover"
    >
    <div class="absolute inset-0 bg-gradient-to-r from-primary-900/85 via-primary-900/45 to-primary-900/5"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary-900/35 via-transparent to-primary-900/25"></div>

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
