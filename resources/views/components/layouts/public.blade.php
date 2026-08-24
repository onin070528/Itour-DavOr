@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? "{$title} · iTOUR Davao Oriental" : 'iTOUR — Discover Davao Oriental' }}</title>
        <meta name="description" content="iTOUR is the official tourism information platform of the Provincial Tourism Office of Davao Oriental — explore destinations, accommodations, restaurants, and tourism establishments across the province.">

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/2.47.0/iconfont/tabler-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-sand-50 text-sand-900">
        <x-navbar />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <x-footer />
    </body>
</html>
