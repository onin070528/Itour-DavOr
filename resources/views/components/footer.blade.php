@php
    $footerColumns = [
        'Discover' => [
            ['label' => 'Home', 'href' => url('/')],
            ['label' => 'Explore', 'href' => route('explore')],
            ['label' => 'Directory', 'href' => route('explore')],
            ['label' => 'Nearby', 'href' => url('/').'#near-you'],
            ['label' => 'Reviews', 'href' => url('/').'#reviews'],
            ['label' => 'About', 'href' => url('/').'#about'],
        ],
        'For Partners' => [
            ['label' => 'Establishment sign in', 'href' => route('login')],
            ['label' => 'LGU tourism office', 'href' => route('login')],
            ['label' => 'Provincial Tourism Office', 'href' => route('login')],
            ['label' => 'Submit tourist feedback', 'href' => url('/').'#reviews'],
        ],
    ];
@endphp

<footer class="border-t border-sand-200 bg-sand-0">
    <div class="mx-auto max-w-[1200px] px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div>
                <x-logo class="text-2xl" />
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-sand-600">
                    Integrated Tourism Information and Monitoring System with Tourist Experience Analytics for the Province of Davao Oriental.
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

            <div id="footer-emergency">
                <h3 class="text-xs font-bold tracking-widest text-sand-500 uppercase">Contact</h3>
                <div class="mt-4 flex flex-col gap-2.5 text-sm">
                    <p class="font-semibold text-sand-900">Provincial Tourism Office</p>
                    <p class="text-primary-700">Capitol Compound, Brgy. Dahican, City of Mati</p>
                    <p class="text-sand-700">
                        (087) 388 3611 &middot;
                        <a href="mailto:tourism@davaooriental.gov.ph" class="text-primary-700 transition-colors hover:text-primary-900">tourism@davaooriental.gov.ph</a>
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-sand-200 pt-6 text-xs text-sand-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} Provincial Tourism Office of Davao Oriental. All rights reserved.</p>
            <p>An official platform of the Provincial Government of Davao Oriental, Republic of the Philippines.</p>
        </div>
    </div>
</footer>
