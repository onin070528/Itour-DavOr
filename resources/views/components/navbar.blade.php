@php
    $navLinks = [
        ['label' => 'Home', 'href' => url('/')],
        ['label' => 'Explore', 'href' => '#destinations'],
        ['label' => 'Plan Your Trip', 'href' => '#near-you'],
        ['label' => 'Reviews', 'href' => '#reviews'],
        ['label' => 'About', 'href' => '#about'],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-sand-200 bg-sand-0/95 backdrop-blur supports-[backdrop-filter]:bg-sand-0/80">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="Primary">
        <a href="{{ url('/') }}" class="flex items-center gap-2 shrink-0">
            <span class="flex h-9 w-9 items-center justify-center rounded-md bg-primary-700 text-sand-0">
                <i class="ti ti-map-2 text-lg" aria-hidden="true"></i>
            </span>
            <span class="leading-tight">
                <span class="block font-display text-lg font-extrabold tracking-tight text-primary-900">iTOUR</span>
                <span class="block text-[10px] font-semibold tracking-widest text-sand-500 uppercase">Davao Oriental</span>
            </span>
        </a>

        <ul class="hidden items-center gap-7 text-sm font-medium text-sand-700 lg:flex">
            @foreach ($navLinks as $link)
                <li>
                    <a href="{{ $link['href'] }}" class="transition-colors hover:text-primary-700">{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>

        <div class="hidden shrink-0 lg:block">
            <a href="#destinations" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900">
                Explore Davao Oriental
            </a>
        </div>

        <button
            type="button"
            id="mobile-menu-button"
            class="inline-flex items-center justify-center rounded-sm border border-sand-300 p-2 text-sand-700 lg:hidden"
            aria-controls="mobile-menu"
            aria-expanded="false"
        >
            <span class="sr-only">Toggle navigation menu</span>
            <i class="ti ti-menu-2 text-xl" id="mobile-menu-icon-open" aria-hidden="true"></i>
            <i class="ti ti-x hidden text-xl" id="mobile-menu-icon-close" aria-hidden="true"></i>
        </button>
    </nav>

    <div id="mobile-menu" class="hidden border-t border-sand-200 bg-sand-0 lg:hidden">
        <ul class="flex flex-col gap-1 px-4 py-3 text-sm font-medium text-sand-700">
            @foreach ($navLinks as $link)
                <li>
                    <a href="{{ $link['href'] }}" class="block rounded-sm px-2 py-2.5 transition-colors hover:bg-sand-100 hover:text-primary-700">{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>
        <div class="border-t border-sand-200 px-4 py-3">
            <a href="#destinations" class="flex items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm">
                Explore Davao Oriental
            </a>
        </div>
    </div>
</header>
