<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header title="Settings" description="Manage your profile, account information, and preferences." />

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0">
        <div data-tabs class="flex border-b border-sand-200 px-2">
            <button type="button" data-tab-target="profile" aria-selected="true" class="border-b-2 border-primary-700 px-4 py-3 text-sm font-semibold text-primary-700">Profile</button>
            <button type="button" data-tab-target="account" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Account Information</button>
            <button type="button" data-tab-target="preferences" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Preferences</button>
            <button type="button" data-tab-target="categories" aria-selected="false" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-sand-500">Categories</button>
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

                <form method="POST" action="{{ route('pto.settings.profile') }}" class="mt-6 flex flex-col gap-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Full Name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input name="name" type="text" value="{{ $user->name }}" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sand-700">Email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input name="email" type="email" value="{{ $user->email }}" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <button type="submit" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div data-tab-panel="account" class="hidden max-w-lg">
                <dl class="flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between border-b border-sand-100 pb-3"><dt class="text-sand-500">Organization</dt><dd class="font-medium text-sand-900">{{ $user->organization_name }}</dd></div>
                    <div class="flex items-center justify-between border-b border-sand-100 pb-3"><dt class="text-sand-500">Coverage</dt><dd class="font-medium text-sand-900">{{ $user->organization_subtitle }}</dd></div>
                    <div class="flex items-center justify-between border-b border-sand-100 pb-3"><dt class="text-sand-500">Role</dt><dd class="font-medium text-sand-900">{{ $user->role->title() }}</dd></div>
                    <div class="flex items-center justify-between pb-3"><dt class="text-sand-500">Account Created</dt><dd class="font-medium text-sand-900">{{ $user->created_at?->format('M j, Y') ?? '—' }}</dd></div>
                </dl>

                <button type="button" data-modal-open="change-password-modal" class="mt-5 rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    Change Password
                </button>
            </div>

            <div data-tab-panel="preferences" class="hidden max-w-lg">
                <form method="POST" action="{{ route('pto.settings.preferences') }}">
                    @csrf
                    <div class="flex flex-col gap-4">
                        @foreach ($preferences as $pref)
                            <label class="flex items-center justify-between gap-4 border-b border-sand-100 pb-3">
                                <span class="text-sm text-sand-800">{{ $pref['label'] }}</span>
                                <input type="checkbox" name="preferences[{{ $pref['key'] }}]" @checked($pref['checked']) class="h-4 w-4 rounded border-sand-300 text-primary-700 focus:ring-primary-500">
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-5 rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        Save Preferences
                    </button>
                </form>
            </div>

            <div data-tab-panel="categories" class="hidden max-w-xl">
                <p class="text-sm text-sand-600">
                    Turning a category's QR switch off never deletes its establishments' QR codes or arrival history — it just refuses new scans with a clear message until switched back on.
                </p>
                <div class="mt-4 flex flex-col divide-y divide-sand-100">
                    @foreach ($categories as $category)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-sand-900">{{ $category->cat_name }}</p>
                                <p class="text-xs text-sand-500">{{ $category->cat_is_qr_enabled ? 'Accepting QR scans' : 'QR scans refused' }}</p>
                            </div>
                            <form method="POST" action="{{ route('pto.settings.categories.toggleQr', $category) }}">
                                @csrf
                                @method('PUT')
                                <button
                                    type="submit"
                                    role="switch"
                                    aria-checked="{{ $category->cat_is_qr_enabled ? 'true' : 'false' }}"
                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors {{ $category->cat_is_qr_enabled ? 'bg-primary-700' : 'bg-sand-300' }}"
                                >
                                    <span class="inline-block h-4.5 w-4.5 transform rounded-full bg-sand-0 shadow transition-transform {{ $category->cat_is_qr_enabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-dashboard.modal id="change-password-modal" title="Change Password" max-width="max-w-sm">
        <form id="change-password-form" method="POST" action="{{ route('pto.settings.password') }}" class="flex flex-col gap-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Current Password <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="current_password" type="password" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">New Password <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="password" type="password" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Confirm New Password <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="password_confirmation" type="password" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="change-password-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Update Password
            </button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
