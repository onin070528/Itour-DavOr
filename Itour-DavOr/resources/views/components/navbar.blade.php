@php
    $navLinks = [
        ['label' => 'Home', 'href' => url('/'), 'active' => request()->routeIs('home')],
        ['label' => 'Explore', 'href' => route('explore'), 'active' => request()->routeIs('explore')],
        ['label' => 'Near Me', 'href' => url('/').'#near-you', 'active' => false],
        ['label' => 'About', 'href' => url('/').'#about', 'active' => false],
    ];

    $authedDashboardRoute = auth()->check() && auth()->user()->role
        ? route(auth()->user()->role->dashboardRouteName())
        : null;
@endphp

<header class="sticky top-0 z-40 border-b border-sand-200 bg-sand-0/95 backdrop-blur supports-[backdrop-filter]:bg-sand-0/80">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="Primary">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 shrink-0">
            <x-logo class="text-2xl" />
            <span class="hidden text-[10px] font-semibold tracking-widest text-sand-500 uppercase sm:block">Davao Oriental</span>
        </a>

        <ul class="hidden items-center gap-7 text-sm font-medium text-sand-700 lg:flex">
            @foreach ($navLinks as $link)
                <li>
                    <a href="{{ $link['href'] }}" @class(['transition-colors hover:text-primary-700', 'text-primary-700 font-semibold' => $link['active']])>{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>

        <div class="hidden shrink-0 items-center gap-3 lg:flex">
            @if ($authedDashboardRoute)
                <a
                    href="{{ $authedDashboardRoute }}"
                    title="Go to my dashboard"
                    aria-label="Go to my dashboard"
                    class="flex h-10 w-10 items-center justify-center rounded-sm border border-sand-300 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                >
                    <i class="ti ti-layout-dashboard text-lg" aria-hidden="true"></i>
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    title="Sign In"
                    aria-label="Sign In"
                    class="flex h-10 w-10 items-center justify-center rounded-sm border border-sand-300 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                >
                    <i class="ti ti-user-circle text-lg" aria-hidden="true"></i>
                </a>
            @endif
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
                    <a href="{{ $link['href'] }}" @class(['block rounded-sm px-2 py-2.5 transition-colors hover:bg-sand-100 hover:text-primary-700', 'text-primary-700 bg-sand-100 font-semibold' => $link['active']])>{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>

        <div class="flex flex-col gap-2 border-t border-sand-200 px-4 py-3">
            @if ($authedDashboardRoute)
                <a href="{{ $authedDashboardRoute }}" class="flex items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm">
                    <i class="ti ti-layout-dashboard" aria-hidden="true"></i>
                    My Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="flex items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm">
                    <i class="ti ti-user-circle" aria-hidden="true"></i>
                    Sign In
                </a>
            @endif
        </div>
    </div>
</header>
