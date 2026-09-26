<x-layouts.auth title="Set New Password">
    <h1 class="mt-6 text-xl sm:text-2xl">Set a new password</h1>
    <p class="mt-1.5 text-sm text-sand-600">Choose a password with at least 8 characters.</p>

    @if ($errors->any())
        <div class="mt-6 rounded-sm bg-danger-bg px-3.5 py-2.5 text-sm text-danger" role="alert">
            {{ $errors->first() }}
            @if ($errors->has('email'))
                <a href="{{ route('password.request') }}" class="font-semibold underline">Request a new link</a>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-sand-700">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autocomplete="username"
                class="min-h-[44px] w-full rounded-sm border border-sand-500 px-3.5 py-2.5 text-base text-sand-900 transition-colors placeholder:text-sand-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 focus-visible:ring-2"
            >
        </div>

        @foreach (['password' => 'New Password', 'password_confirmation' => 'Confirm New Password'] as $field => $label)
            <div>
                <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium text-sand-700">{{ $label }}</label>
                <div data-password-field class="relative">
                    <input
                        id="{{ $field }}"
                        type="password"
                        name="{{ $field }}"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        @if ($loop->first) autofocus @endif
                        aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}"
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
        @endforeach

        <button
            type="submit"
            class="mt-1 inline-flex min-h-[44px] items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
        >
            Save Password
        </button>
    </form>

    <a
        href="{{ route('login') }}"
        class="mt-4 flex items-center justify-center gap-1.5 rounded-sm text-center text-sm font-semibold text-primary-700 transition-colors hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
    >
        <i class="ti ti-arrow-left" aria-hidden="true"></i>
        Back to sign in
    </a>
</x-layouts.auth>
