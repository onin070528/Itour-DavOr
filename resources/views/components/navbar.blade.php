{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public navigation bar.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $navLinks = [
        ['label' => 'Home', 'href' => url('/'), 'active' => request()->routeIs('home')],
        ['label' => 'Explore', 'href' => route('explore'), 'active' => request()->routeIs('explore')],
        ['label' => 'Nearby', 'href' => url('/').'#near-you', 'active' => false],
        ['label' => 'Reviews', 'href' => url('/').'#reviews', 'active' => false],
        ['label' => 'Hotlines', 'href' => route('hotlines'), 'active' => request()->routeIs('hotlines')],
    ];

    $authedDashboardRoute = auth()->check() && auth()->user()->usr_role
        ? route(auth()->user()->usr_role->dashboardRouteName())
        : null;
@endphp

{{-- sand-50 + sand-200 stand in for the spec's literal #faf6f0/stone-200 —
     sand-50 (#f8f6ef) is this app's existing near-identical warm off-white
     token, and sand-200 is its existing neutral border token; no new
     bespoke color is introduced. Fully solid (no opacity or blur) so the
     logo and nav links stay crisp regardless of what's behind it. sticky
     so it stays in view while scrolling or navigating between the landing
     page's in-page sections (Nearby/Reviews/About). --}}
<header class="sticky top-0 z-30 w-full border-b border-sand-200 bg-sand-50 shadow-sm">
    <nav class="mx-auto flex max-w-[1200px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="Primary">
        <a href="{{ url('/') }}" class="inline-flex shrink-0 items-center gap-2.5">
            {{-- Official DAVOR Tourism seal, alongside a stacked iTOUR
                 wordmark + "Davao Oriental" subtitle. sand-300 (this app's
                 neutral border token) stands in for the slate-300 divider
                 originally specified — this codebase has no slate token. --}}
            <img
                src="{{ asset('storage/itour-images/davor-tourism.jpg') }}"
                alt="Provincial Government of Davao Oriental — Tourism Office seal"
                class="h-9 w-9 shrink-0 rounded-full"
            >
            <span class="h-9 w-px bg-sand-300" aria-hidden="true"></span>
            <span class="flex flex-col justify-center">
                <x-logo class="text-lg leading-none" />
                <span class="mt-1 text-[10px] leading-none font-semibold tracking-widest uppercase text-sand-500">Davao Oriental</span>
            </span>
        </a>

        {{-- Active state: text-primary-700 (this app's existing teal) + a
             solid accent-500 underline (this app's existing orange accent
             color — already used for the notification dot elsewhere) below
             the active link only. Inactive: sand-900 (this app's darkest
             neutral, standing in for the spec's text-stone-800) for high
             contrast against the beige header, no underline, hover fades to
             the same teal. Home/Explore/Hotlines are real routes, so their
             initial active state is server-rendered via request()->routeIs().
             Nearby/Reviews are same-page anchor links with no route of their
             own — data-nav-link/data-nav-variant let initNavScrollSpy()
             (resources/js/app.js) repaint Home/Nearby/Reviews live as their
             section scrolls into view; Explore/Hotlines have neither
             attribute, so the scroll-spy never touches them. --}}
        <ul class="hidden items-center gap-7 text-sm font-medium lg:flex">
            @foreach ($navLinks as $link)
                <li>
                    <a
                        href="{{ $link['href'] }}"
                        @if (! in_array($link['label'], ['Explore', 'Hotlines'], true))
                            data-nav-link="{{ $link['label'] }}"
                            data-nav-variant="underline"
                        @endif
                        @class([
                            'border-b-2 pb-1 transition-all duration-200',
                            'border-accent-500 font-semibold text-primary-700' => $link['active'],
                            'border-transparent text-sand-900 hover:text-primary-700' => ! $link['active'],
                        ])
                    >{{ $link['label'] }}</a>
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
                    <a
                        href="{{ $link['href'] }}"
                        @if (! in_array($link['label'], ['Explore', 'Hotlines'], true))
                            data-nav-link="{{ $link['label'] }}"
                            data-nav-variant="pill"
                        @endif
                        @class(['block rounded-sm px-2 py-2.5 transition-all duration-200 hover:bg-sand-100 hover:text-primary-700', 'text-primary-700 bg-sand-100 font-semibold' => $link['active']])
                    >{{ $link['label'] }}</a>
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
