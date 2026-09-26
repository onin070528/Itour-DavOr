@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }} · iTOUR Davao Oriental</title>

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @keyframes login-card-in {
                from { opacity: 0; transform: translateY(12px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .login-card-enter {
                animation: login-card-in 0.45s ease-out both;
            }
            @media (prefers-reduced-motion: reduce) {
                .login-card-enter { animation: none; }
            }
        </style>
    </head>
    <body class="relative flex min-h-screen items-center justify-center overflow-hidden bg-primary-900 px-4 py-12">
        {{-- Ambient Davao Oriental backdrop — one subdued photo behind the whole
             page (blurred + scrimmed) rather than a second image competing
             inside the card itself. Purely decorative. --}}
        <div class="absolute inset-0" aria-hidden="true">
            <img
                src="{{ asset('storage/itour-images/pujada-bay.jpg') }}"
                alt=""
                class="h-full w-full scale-105 object-cover blur-[3px]"
            >
            <div class="absolute inset-0 bg-gradient-to-b from-primary-900/70 via-primary-900/35 to-primary-900/70"></div>
            <div class="absolute inset-0 bg-primary-900/10"></div>
        </div>

        <div class="login-card-enter relative w-full max-w-md rounded-lg border border-sand-200 bg-sand-0 p-6 shadow-xl sm:p-8">
            <a
                href="{{ url('/') }}"
                class="inline-flex items-center gap-2 rounded-sm transition-opacity hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
            >
                <x-logo class="text-xl" />
            </a>
            <p class="mt-1 text-xs font-semibold tracking-widest text-sand-600 uppercase">Davao Oriental</p>

            {{ $slot }}
        </div>
    </body>
</html>
