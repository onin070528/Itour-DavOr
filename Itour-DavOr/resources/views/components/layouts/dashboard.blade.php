@props([
    'user',
    'navSections' => [],
    'pageTitle' => 'Dashboard',
    'accountHeading' => 'Account',
    'profileHref' => '#',
    'settingsHref' => '#',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $pageTitle }} · iTOUR</title>

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">

        @vite(array_filter([
            'resources/css/app.css',
            'resources/js/app.js',
            in_array($user->role, [\App\Enums\UserRole::PtoAdministrator, \App\Enums\UserRole::Lgu, \App\Enums\UserRole::Establishment], true) ? 'resources/js/dashboard.js' : null,
            $user->role === \App\Enums\UserRole::Establishment ? 'resources/js/establishment.js' : null,
        ]))
    </head>
    <body class="flex min-h-screen bg-sand-100 text-sand-900">
        <aside class="flex w-64 shrink-0 flex-col bg-primary-900 text-sand-0">
            <div class="flex items-center justify-between px-5 pt-6 pb-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <x-logo :dark="true" class="text-lg" />
                </a>
                <span class="rounded-sm bg-white/10 px-2 py-1 text-[10px] font-bold tracking-widest">{{ $user->role->badge() }}</span>
            </div>

            <div class="mx-4 mb-5 rounded-md bg-white/10 px-3.5 py-3">
                <p class="text-sm font-semibold text-sand-0">{{ $user->organization_name }}</p>
                <p class="text-xs text-white/65">{{ $user->organization_subtitle }}</p>
            </div>

            <nav class="flex-1 px-3 pb-4" aria-label="Dashboard" data-nav>
                @foreach ($navSections as $heading => $items)
                    <p class="mt-4 mb-1.5 px-2 text-[10px] font-bold tracking-widest text-white/45 uppercase">{{ $heading }}</p>
                    <ul class="flex flex-col gap-0.5">
                        @foreach ($items as $item)
                            <li>
                                @if (! empty($item['children']))
                                    <button
                                        type="button"
                                        data-nav-toggle
                                        aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
                                        @class([
                                            'flex w-full items-center justify-between gap-2 rounded-sm px-2.5 py-2 text-left text-sm transition-colors',
                                            'font-semibold text-sand-0' => $item['active'],
                                            'text-white/78 hover:bg-white/10 hover:text-sand-0' => ! $item['active'],
                                        ])
                                    >
                                        <span class="flex items-center gap-2.5">
                                            <i class="ti {{ $item['icon'] }} text-base" aria-hidden="true"></i>
                                            {{ $item['label'] }}
                                        </span>
                                        <i class="ti ti-chevron-down text-sm transition-transform" data-nav-chevron aria-hidden="true"></i>
                                    </button>
                                    <ul @class(['flex flex-col gap-0.5 py-0.5 pl-7', 'hidden' => ! $item['active']]) data-nav-submenu>
                                        @foreach ($item['children'] as $child)
                                            <li>
                                                <a
                                                    href="{{ $child['href'] }}"
                                                    @class([
                                                        'flex items-center gap-2.5 rounded-sm px-2.5 py-2 text-sm transition-colors',
                                                        'bg-white/12 font-semibold text-sand-0' => $child['active'] ?? false,
                                                        'text-white/78 hover:bg-white/10 hover:text-sand-0' => ! ($child['active'] ?? false),
                                                    ])
                                                >
                                                    <i class="ti {{ $child['icon'] }} text-sm" aria-hidden="true"></i>
                                                    {{ $child['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <a
                                        href="{{ $item['href'] }}"
                                        @class([
                                            'flex items-center justify-between gap-2 rounded-sm px-2.5 py-2 text-sm transition-colors',
                                            'bg-white/12 font-semibold text-sand-0' => $item['active'] ?? false,
                                            'text-white/78 hover:bg-white/10 hover:text-sand-0' => ! ($item['active'] ?? false),
                                        ])
                                    >
                                        <span class="flex items-center gap-2.5">
                                            <i class="ti {{ $item['icon'] }} text-base" aria-hidden="true"></i>
                                            {{ $item['label'] }}
                                        </span>
                                        @if ($item['soon'] ?? ! ($item['active'] ?? false))
                                            <span class="rounded-sm bg-white/10 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-white/50 uppercase">Soon</span>
                                        @endif
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            <div class="border-t border-white/10 px-3 py-4">
                <p class="mb-1.5 px-2 text-[10px] font-bold tracking-widest text-white/45 uppercase">{{ $accountHeading }}</p>
                <ul class="flex flex-col gap-0.5 text-sm text-white/78">
                    <li>
                        <a href="{{ $profileHref }}" class="flex items-center gap-2.5 rounded-sm px-2.5 py-2 hover:bg-white/10 hover:text-sand-0">
                            <i class="ti ti-user-circle text-base" aria-hidden="true"></i>
                            My Profile
                        </a>
                    </li>
                    <li>
                        <a href="{{ $settingsHref }}" class="flex items-center gap-2.5 rounded-sm px-2.5 py-2 hover:bg-white/10 hover:text-sand-0">
                            <i class="ti ti-settings text-base" aria-hidden="true"></i>
                            Settings
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-sm px-2.5 py-2 text-left hover:bg-white/10 hover:text-sand-0">
                                <i class="ti ti-logout text-base" aria-hidden="true"></i>
                                Log Out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between gap-4 border-b border-sand-200 bg-sand-0 px-6 py-3.5">
                <p class="text-sm text-sand-500">
                    {{ $user->role->title() }} <span class="mx-1 text-sand-300">/</span> <span class="font-semibold text-sand-900">{{ $pageTitle }}</span>
                </p>

                <div class="flex items-center gap-4">
                    <button type="button" class="relative text-sand-600" aria-label="Notifications">
                        <i class="ti ti-bell text-lg" aria-hidden="true"></i>
                        <span class="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-accent-500"></span>
                    </button>

                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 font-display text-sm font-bold text-primary-700">
                            {{ collect(explode(' ', $user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                        </span>
                        <span class="hidden leading-tight sm:block">
                            <span class="block text-sm font-semibold text-sand-900">{{ $user->name }}</span>
                            <span class="block text-xs text-sand-500">{{ $user->role->title() }}</span>
                        </span>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6">
                {{ $slot }}
            </main>
        </div>

        {{-- Shared confirmation dialog for archive / enable / disable actions.
             Triggered via [data-confirm-trigger] — see resources/js/pto.js. --}}
        <div id="confirm-modal" data-modal class="fixed inset-0 z-50 hidden">
            <div data-modal-backdrop class="flex min-h-full items-center justify-center bg-sand-900/50 p-4">
                <div class="w-full max-w-sm rounded-lg bg-sand-0 p-5 shadow-md">
                    <p data-confirm-title class="font-display text-base font-bold text-sand-900">Are you sure?</p>
                    <p data-confirm-message class="mt-1.5 text-sm text-sand-600">This action cannot be undone.</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" data-modal-close class="rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-700 hover:border-sand-400">
                            Cancel
                        </button>
                        <button type="button" data-confirm-button class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
