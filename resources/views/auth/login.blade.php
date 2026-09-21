<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Sign In · iTOUR Davao Oriental</title>

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

            <h1 class="mt-6 text-xl sm:text-2xl">Welcome to iTOUR</h1>
            <p class="mt-1.5 text-sm text-sand-600">For the Provincial Tourism Office, LGU Tourism Offices, and accredited establishments.</p>

            <div
                id="login-error"
                class="mt-6 rounded-sm bg-danger-bg px-3.5 py-2.5 text-sm text-danger {{ $errors->any() ? '' : 'hidden' }}"
                role="alert"
                aria-live="assertive"
            >
                {{ $errors->first() }}
            </div>

            <form id="login-form" method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4" novalidate>
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-sand-700">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="name@example.com"
                        required
                        autofocus
                        autocomplete="username"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        class="min-h-[44px] w-full rounded-sm border border-sand-500 px-3.5 py-2.5 text-base text-sand-900 transition-colors placeholder:text-sand-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 focus-visible:ring-2"
                    >
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-sand-700">Password</label>
                    <div data-password-field class="relative">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            class="min-h-[44px] w-full rounded-sm border border-sand-500 px-3.5 py-2.5 pr-12 text-base text-sand-900 transition-colors placeholder:text-sand-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 focus-visible:ring-2"
                        >
                        <button
                            type="button"
                            data-password-toggle
                            aria-pressed="false"
                            aria-label="Show password"
                            class="absolute inset-y-0 right-0 flex min-h-[44px] w-11 items-center justify-center rounded-sm text-sand-500 transition-colors hover:text-sand-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
                        >
                            <i class="ti ti-eye" data-icon-show aria-hidden="true"></i>
                            <i class="ti ti-eye-off hidden" data-icon-hide aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    id="login-submit"
                    class="mt-1 inline-flex min-h-[44px] items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0 disabled:cursor-not-allowed disabled:opacity-75"
                >
                    <i class="ti ti-loader-2 hidden animate-spin" data-submit-spinner aria-hidden="true"></i>
                    <span data-submit-label>Sign In</span>
                </button>
            </form>

            <a
                href="{{ url('/') }}"
                class="mt-4 block rounded-sm text-center text-sm font-semibold text-primary-700 transition-colors hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
            >
                Back to the public tourism site
            </a>
        </div>
    </body>
</html>
