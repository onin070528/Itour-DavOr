<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header title="Settings" description="Manage your account profile, contact information, and preferences." />

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0">
        <div data-tabs class="flex border-b border-sand-200 px-2">
            <button type="button" data-tab-target="profile" aria-selected="true" class="border-b-2 border-primary-700 px-4 py-3 text-sm font-semibold text-primary-700">Account Profile</button>
            <button type="button" data-tab-target="contact" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Contact Information</button>
            <button type="button" data-tab-target="preferences" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Notifications</button>
        </div>

        <div class="p-6">
            <div data-tab-panel="profile" class="max-w-lg">
                <div class="flex items-center gap-4">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 font-display text-xl font-bold text-primary-700">
                        {{ collect(explode(' ', $user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                    </span>
                    <div>
                        <p class="font-display text-base font-bold text-sand-900">{{ $user->name }}</p>
                        <p class="text-sm text-sand-500">{{ $user->role->title() }} · {{ $establishmentName }}</p>
                    </div>
                </div>

                <form class="mt-6 flex flex-col gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Full Name</label>
                        <input type="text" value="{{ $user->name }}" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Login Email</label>
                        <input type="email" value="{{ $user->email }}" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <button type="button" data-toast-trigger data-toast-message="Account profile saved." class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                            Save Changes
                        </button>
                    </div>
                </form>

                <button type="button" data-modal-open="change-password-modal" class="mt-5 rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    Change Password
                </button>
            </div>

            <div data-tab-panel="contact" class="hidden max-w-lg">
                <p class="text-sm text-sand-600">
                    Your establishment's public contact details are managed on the
                    <a href="{{ route('establishment.profile') }}" class="font-semibold text-primary-700 hover:text-primary-900">Establishment Profile</a>
                    page — they're what tourists see on your iTOUR listing.
                </p>
                <a href="{{ route('establishment.profile') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-building-store" aria-hidden="true"></i>
                    Go to Establishment Profile
                </a>
            </div>

            <div data-tab-panel="preferences" class="hidden max-w-lg">
                <div class="flex flex-col gap-4">
                    @foreach ([
                        ['label' => 'Email me when a new tourist arrival is recorded', 'checked' => false],
                        ['label' => 'Email me when new feedback is left about my establishment', 'checked' => true],
                        ['label' => 'Email me a monthly visitor summary', 'checked' => true],
                    ] as $pref)
                        <label class="flex items-center justify-between gap-4 border-b border-sand-100 pb-3">
                            <span class="text-sm text-sand-800">{{ $pref['label'] }}</span>
                            <input type="checkbox" @checked($pref['checked']) class="h-4 w-4 rounded border-sand-300 text-primary-700 focus:ring-primary-500">
                        </label>
                    @endforeach
                </div>
                <button type="button" data-toast-trigger data-toast-message="Preferences saved." class="mt-5 rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    Save Preferences
                </button>
            </div>
        </div>
    </div>

    <x-dashboard.modal id="change-password-modal" title="Change Password" max-width="max-w-sm">
        <form class="flex flex-col gap-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Current Password</label>
                <input type="password" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">New Password</label>
                <input type="password" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Confirm New Password</label>
                <input type="password" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-700 hover:border-sand-400">Cancel</button>
            <button type="button" data-modal-close data-toast-message="Password updated." class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Update Password
            </button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
