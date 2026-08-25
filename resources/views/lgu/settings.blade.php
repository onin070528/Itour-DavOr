<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header title="Settings" description="Manage your profile, account information, and preferences." />

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0">
        <div data-tabs class="flex border-b border-sand-200 px-2">
            <button type="button" data-tab-target="profile" aria-selected="true" class="border-b-2 border-primary-700 px-4 py-3 text-sm font-semibold text-primary-700">Profile</button>
            <button type="button" data-tab-target="account" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Account Information</button>
            <button type="button" data-tab-target="preferences" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Preferences</button>
        </div>

        <div class="p-6">
            <div data-tab-panel="profile" class="max-w-lg">
                <div class="flex items-center gap-4">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 font-display text-xl font-bold text-primary-700">
                        {{ collect(explode(' ', $user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                    </span>
                    <div>
                        <p class="font-display text-base font-bold text-sand-900">{{ $user->name }}</p>
                        <p class="text-sm text-sand-500">{{ $user->role->title() }}</p>
                    </div>
                </div>

                <form class="mt-6 flex flex-col gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Full Name</label>
                        <input type="text" value="{{ $user->name }}" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Email</label>
                        <input type="email" value="{{ $user->email }}" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <button type="button" data-toast-trigger data-toast-message="Profile changes saved." class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div data-tab-panel="account" class="hidden max-w-lg">
                <dl class="flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between border-b border-sand-100 pb-3"><dt class="text-sand-500">Municipal Tourism Office</dt><dd class="font-medium text-sand-900">{{ $user->organization_name }}</dd></div>
                    <div class="flex items-center justify-between border-b border-sand-100 pb-3">
                        <dt class="text-sand-500">Assigned Municipality</dt>
                        <dd class="flex items-center gap-1.5 font-medium text-sand-900">
                            <i class="ti ti-map-pin text-primary-700" aria-hidden="true"></i>
                            {{ $user->organization_subtitle }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between border-b border-sand-100 pb-3"><dt class="text-sand-500">Role</dt><dd class="font-medium text-sand-900">{{ $user->role->title() }}</dd></div>
                    <div class="flex items-center justify-between pb-3"><dt class="text-sand-500">Account Created</dt><dd class="font-medium text-sand-900">{{ $user->created_at?->format('M j, Y') ?? '—' }}</dd></div>
                </dl>
                <p class="mt-4 flex items-start gap-1.5 text-xs text-sand-500">
                    <i class="ti ti-info-circle mt-0.5 shrink-0" aria-hidden="true"></i>
                    Your assigned municipality is set by the Provincial Tourism Office and cannot be changed from this page.
                </p>

                <button type="button" data-modal-open="change-password-modal" class="mt-5 rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    Change Password
                </button>
            </div>

            <div data-tab-panel="preferences" class="hidden max-w-lg">
                <div class="flex flex-col gap-4">
                    @foreach ([
                        ['label' => 'Email me a weekly municipal tourism summary', 'checked' => true],
                        ['label' => 'Notify me when tourist feedback is flagged negative', 'checked' => true],
                        ['label' => 'Notify me when a new establishment registers in my municipality', 'checked' => false],
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
