<x-layouts.auth title="Forgot Password">
    <h1 class="mt-6 text-xl sm:text-2xl">Forgot your password?</h1>
    <p class="mt-1.5 text-sm text-sand-600">Enter the email for your iTOUR account and we'll send you a link to set a new password. New accounts use this to set their password for the first time.</p>

    @if (session('status'))
        <div class="mt-6 rounded-sm bg-success-bg px-3.5 py-2.5 text-sm text-success" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-sm bg-danger-bg px-3.5 py-2.5 text-sm text-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 flex flex-col gap-4">
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

        <button
            type="submit"
            class="mt-1 inline-flex min-h-[44px] items-center justify-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-0"
        >
            Email Reset Link
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
