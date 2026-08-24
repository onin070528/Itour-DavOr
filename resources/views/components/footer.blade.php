@php
    $footerColumns = [
        'Explore' => [
            ['label' => 'Destinations', 'href' => '#destinations'],
            ['label' => 'Tourism Directory', 'href' => '#directory'],
            ['label' => 'Reviews', 'href' => '#reviews'],
            ['label' => 'Near You', 'href' => '#near-you'],
        ],
        'Information' => [
            ['label' => 'About iTOUR', 'href' => '#about'],
            ['label' => 'Tourism Information', 'href' => '#'],
            ['label' => 'Contact', 'href' => '#'],
        ],
        'Emergency' => [
            ['label' => 'Emergency Hotlines', 'href' => '#'],
            ['label' => 'Tourism Assistance', 'href' => '#'],
        ],
    ];
@endphp

<footer class="border-t border-sand-200 bg-sand-0">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-primary-700 text-sand-0">
                        <i class="ti ti-map-2 text-lg" aria-hidden="true"></i>
                    </span>
                    <span class="font-display text-lg font-extrabold tracking-tight text-primary-900">iTOUR</span>
                </div>
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-sand-600">
                    Integrated Tourism Information and Monitoring System — the official digital platform of the Provincial Tourism Office of Davao Oriental.
                </p>
            </div>

            @foreach ($footerColumns as $heading => $links)
                <div>
                    <h3 class="text-xs font-bold tracking-widest text-sand-500 uppercase">{{ $heading }}</h3>
                    <ul class="mt-4 flex flex-col gap-2.5 text-sm text-sand-700">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ $link['href'] }}" class="transition-colors hover:text-primary-700">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-sand-200 pt-6 text-xs text-sand-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} Provincial Tourism Office of Davao Oriental. All rights reserved.</p>
            <p>An official platform of the Provincial Government of Davao Oriental, Republic of the Philippines.</p>
        </div>
    </div>
</footer>
