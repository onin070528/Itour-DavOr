<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Sign In · iTOUR Davao Oriental</title>

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-sand-100 px-4 py-12">
        <div class="w-full max-w-md overflow-hidden rounded-lg border border-sand-200 bg-sand-0 shadow-md">
            {{-- Subtle Davao Oriental visual — a header band, not a split-screen layout. --}}
            <div class="relative h-32 sm:h-40">
                <img
                    src="{{ asset('storage/itour-images/hero-dahican-sunrise.jpg') }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-primary-900/85 via-primary-900/25 to-primary-900/10"></div>

                <a href="{{ url('/') }}" class="absolute inset-0 flex flex-col items-center justify-end gap-1 pb-4">
                    <x-logo :dark="true" class="text-xl" />
                    <span class="text-[10px] font-semibold tracking-widest text-white/80 uppercase">Davao Oriental</span>
                </a>
            </div>

            <div class="p-8">
                <h1 class="text-xl sm:text-2xl">Welcome to iTOUR</h1>
                <p class="mt-1.5 text-sm text-sand-600">Sign in to your account.</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-sm bg-danger-bg px-3.5 py-2.5 text-sm text-danger" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-sand-700">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            class="w-full rounded-sm border border-sand-300 px-3.5 py-2.5 text-sm text-sand-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-sand-700">Password</label>
                        <div data-password-field class="relative">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                class="w-full rounded-sm border border-sand-300 px-3.5 py-2.5 pr-10 text-sm text-sand-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                            <button
                                type="button"
                                data-password-toggle
                                aria-pressed="false"
                                aria-label="Show password"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-sand-500 hover:text-sand-800"
                            >
                                <i class="ti ti-eye" data-icon-show aria-hidden="true"></i>
                                <i class="ti ti-eye-off hidden" data-icon-hide aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="login-submit" class="mt-1 inline-flex items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900">
                        Sign In
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-sand-500">Demo accounts use the password <code class="rounded-sm bg-sand-100 px-1 py-0.5">password</code>.</p>

                <a href="{{ url('/') }}" class="mt-4 block text-center text-sm font-semibold text-primary-700 hover:text-primary-900">
                    Back to the public tourism site
                </a>
            </div>
        </div>
    </body>
</html>
