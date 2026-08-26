<section class="relative overflow-hidden">
    <img
        src="{{ asset('storage/itour-images/hero-dahican-sunrise.jpg') }}"
        alt="Aerial view of Dahican Beach's coastline at sunrise, Davao Oriental"
        class="absolute inset-0 h-full w-full object-cover"
    >
    <div class="absolute inset-0 bg-gradient-to-r from-primary-900/90 via-primary-900/55 to-primary-900/10"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <span class="inline-flex items-center gap-2 rounded-sm bg-white/10 px-3 py-1.5 text-xs font-semibold text-sand-0 ring-1 ring-inset ring-white/20">
            <i class="ti ti-shield-check text-accent-300" aria-hidden="true"></i>
            Official tourism platform · Province of Davao Oriental
        </span>

        <h1 class="mt-6 max-w-2xl text-4xl font-extrabold tracking-tight text-sand-0 sm:text-5xl">
            Discover Davao Oriental
        </h1>
        <p class="mt-4 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">
            Explore destinations, accommodations, restaurants, activities, and other tourism establishments across the province — where the Philippines greets the sunrise first.
        </p>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('explore') }}" class="inline-flex items-center justify-center gap-2 rounded-sm bg-accent-500 px-6 py-3 text-sm font-semibold text-sand-0 shadow-md transition-colors hover:bg-accent-600">
                <i class="ti ti-compass" aria-hidden="true"></i>
                Explore Destinations
            </a>
            <a href="#near-you" class="inline-flex items-center justify-center gap-2 rounded-sm border border-white/30 bg-white/5 px-6 py-3 text-sm font-semibold text-sand-0 transition-colors hover:bg-white/15">
                <i class="ti ti-map-pin-search" aria-hidden="true"></i>
                Find Places Near You
            </a>
        </div>

        <form action="{{ route('explore') }}" method="GET" class="mt-10 flex max-w-xl items-center gap-2 rounded-md bg-sand-0 p-2 shadow-md">
            <i class="ti ti-search ml-2 text-sand-500" aria-hidden="true"></i>
            <label for="hero-search" class="sr-only">Search a destination, accommodation, restaurant, or service</label>
            <input
                id="hero-search"
                type="search"
                name="q"
                placeholder="Search a destination, resort, restaurant or service..."
                class="w-full flex-1 border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none"
            >
            <button type="submit" class="shrink-0 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 transition-colors hover:bg-primary-900">
                Search
            </button>
        </form>

        <div class="mt-4 flex max-w-xl flex-wrap gap-2">
            @foreach ([
                ['icon' => 'ti-map-pin', 'label' => 'Destinations', 'href' => route('explore', ['category' => 'destinations'])],
                ['icon' => 'ti-bed', 'label' => 'Accommodation', 'href' => route('explore', ['category' => 'accommodation'])],
                ['icon' => 'ti-tools-kitchen-2', 'label' => 'Restaurants', 'href' => route('explore', ['category' => 'restaurants'])],
                ['icon' => 'ti-bus', 'label' => 'Transportation', 'href' => route('explore', ['category' => 'transportation'])],
                ['icon' => 'ti-first-aid-kit', 'label' => 'Emergency', 'href' => '#footer-emergency'],
            ] as $pill)
                <a href="{{ $pill['href'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-sand-0 backdrop-blur transition-colors hover:bg-white/20">
                    <i class="ti {{ $pill['icon'] }}" aria-hidden="true"></i>
                    {{ $pill['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</section>
