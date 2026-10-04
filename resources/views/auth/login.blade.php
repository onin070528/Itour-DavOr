<x-layouts.auth title="Sign In">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <h1 class="mt-6 text-xl sm:text-2xl">Welcome to iTOUR</h1>
    <p class="mt-1.5 text-sm text-sand-600">For the Provincial Tourism Office, LGU Tourism Offices, and accredited establishments.</p>

    @if (session('status'))
        <div class="mt-6 rounded-sm bg-success-bg px-3.5 py-2.5 text-sm text-success" role="status">
            {{ session('status') }}
        </div>
    @endif

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
            <div class="mb-1.5 flex items-center justify-between gap-2">
                <label for="password" class="block text-sm font-medium text-sand-700">Password</label>
                <a
                    href="{{ route('password.request') }}"
                    class="rounded-sm text-sm font-semibold text-primary-700 transition-colors hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
                >
                    Forgot password?
                </a>
            </div>
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

        {{-- Cloudflare Turnstile — see App\Rules\Turnstile for the
             server-side verification this widget's token is checked
             against. Site key only; the secret key never leaves the
             server (config/services.php, TURNSTILE_SECRET_KEY). Centered
             and set off with its own label/border since the widget has a
             fixed width narrower than the rest of the form. --}}
        <div>
            <p class="mb-1.5 block text-sm font-medium text-sand-700">Security Check</p>
            <div class="flex justify-center rounded-sm border border-sand-300 bg-sand-50 py-3">
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
            </div>
            @error('cf-turnstile-response')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            id="login-submit"
            class="mt-2 inline-flex min-h-[44px] items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0 disabled:cursor-not-allowed disabled:opacity-75"
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
</x-layouts.auth>
