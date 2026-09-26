<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Record Arrival"
        description="Log a guest arrival at {{ $establishmentName }}."
    />

    {{-- Fallback for a guest who can't scan the QR — same Local/International x
         Male/Female x Age-group companion matrix as the public self-checkin form
         (resources/views/lgu/establishmentQR.blade.php), reactive via Alpine.js
         (arrivalForm() in resources/js/establishment.js) instead of the old
         Enter/Review/Submitted wizard, with the totals summary pinned in a
         sticky right-hand panel while the form scrolls. --}}
    {{-- Js::from(), not @json() — @json() doesn't HTML-attribute-escape its
         output, so an unescaped double quote inside it truncates x-data early. --}}
    <div
        class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8"
        x-data="arrivalForm({{ \Illuminate\Support\Js::from(route('establishment.arrivals.store')) }}, {{ \Illuminate\Support\Js::from(now()->toDateString()) }})"
    >
        {{-- items-stretch (the default — not items-start) is intentional: with
             items-start the right column's grid cell collapses to the height of
             just the summary card, leaving no room for `sticky` to do anything
             as the taller left column scrolls past, so the panel would just
             scroll away instead of staying pinned. Stretching the (invisible)
             cell height to match the left column is what makes top-6 actually
             stick. --}}
        <form @submit.prevent="submit" x-ref="form" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Left column: form inputs --}}
            <div class="flex flex-col gap-6 lg:col-span-8">
                {{-- Visit Details --}}
                <div class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                    <h2 class="font-display text-sm font-bold text-sand-900">Visit Details</h2>

                    <div class="mt-4">
                        <p class="mb-2 text-xs font-semibold text-sand-700">Visit Type</p>
                        <div class="inline-flex rounded-md border border-sand-300 p-1" role="radiogroup" aria-label="Visit type">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="visitType === 'Daytour'"
                                @click="visitType = 'Daytour'"
                                :class="visitType === 'Daytour' ? 'bg-primary-700 text-sand-0' : 'text-sand-600 hover:text-sand-900'"
                                class="rounded-sm px-4 py-2 text-sm font-semibold transition-colors"
                            >
                                ☀️ Daytour
                            </button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="visitType === 'Overnight'"
                                @click="visitType = 'Overnight'"
                                :class="visitType === 'Overnight' ? 'bg-primary-700 text-sand-0' : 'text-sand-600 hover:text-sand-900'"
                                class="rounded-sm px-4 py-2 text-sm font-semibold transition-colors"
                            >
                                🌙 Overnight
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="arrival-date" class="mb-1 block text-xs font-semibold text-sand-700">Date</label>
                            <input id="arrival-date" type="date" x-model="date" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                        </div>
                        <div>
                            <label for="lead-visitor-name" class="mb-1 block text-xs font-semibold text-sand-700">Lead Visitor Name <span class="font-normal text-sand-500">(optional)</span></label>
                            <input id="lead-visitor-name" type="text" x-model="leadVisitorName" placeholder="e.g. Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                @foreach ([
                    ['key' => 'local', 'icon' => 'ti-home', 'label' => 'Local / Domestic Guests'],
                    ['key' => 'foreign', 'icon' => 'ti-world', 'label' => 'International / Foreign Guests'],
                ] as $group)
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <i class="ti {{ $group['icon'] }} text-sm" aria-hidden="true"></i>
                            {{ $group['label'] }}
                        </p>

                        <div class="mt-3 overflow-hidden rounded-sm border border-sand-200">
                            <div class="grid grid-cols-3 bg-sand-100 text-[10px] font-semibold tracking-wide text-sand-500 uppercase">
                                <span class="px-3 py-2">Age Group</span>
                                <span class="border-l border-sand-200 px-2 py-2 text-center">Male</span>
                                <span class="border-l border-sand-200 px-2 py-2 text-center">Female</span>
                            </div>

                            <template x-for="row in ageRows" :key="row.key">
                                <div class="grid grid-cols-3 items-center border-t border-sand-200">
                                    <div class="px-3 py-2">
                                        <p class="text-xs font-semibold text-sand-900" x-text="row.label"></p>
                                    </div>
                                    <div class="flex justify-center gap-1.5 border-l border-sand-200 py-2">
                                        <button
                                            type="button"
                                            @click="dec('{{ $group['key'] }}', row.key, 'male')"
                                            class="flex h-7 w-7 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                                            :aria-label="'Decrease male ' + row.label"
                                        >
                                            <i class="ti ti-minus text-xs" aria-hidden="true"></i>
                                        </button>
                                        <span class="flex h-7 min-w-8 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sm font-bold text-sand-900" x-text="guests['{{ $group['key'] }}'][row.key].male"></span>
                                        <button
                                            type="button"
                                            @click="inc('{{ $group['key'] }}', row.key, 'male')"
                                            class="flex h-7 w-7 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                                            :aria-label="'Increase male ' + row.label"
                                        >
                                            <i class="ti ti-plus text-xs" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="flex justify-center gap-1.5 border-l border-sand-200 py-2">
                                        <button
                                            type="button"
                                            @click="dec('{{ $group['key'] }}', row.key, 'female')"
                                            class="flex h-7 w-7 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                                            :aria-label="'Decrease female ' + row.label"
                                        >
                                            <i class="ti ti-minus text-xs" aria-hidden="true"></i>
                                        </button>
                                        <span class="flex h-7 min-w-8 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sm font-bold text-sand-900" x-text="guests['{{ $group['key'] }}'][row.key].female"></span>
                                        <button
                                            type="button"
                                            @click="inc('{{ $group['key'] }}', row.key, 'female')"
                                            class="flex h-7 w-7 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
                                            :aria-label="'Increase female ' + row.label"
                                        >
                                            <i class="ti ti-plus text-xs" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Right column: sticky live summary + submit --}}
            <div class="lg:col-span-4">
                <div class="sticky top-6 flex flex-col gap-4 rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                    <div>
                        <h2 class="font-display text-sm font-bold text-sand-900">Registration Summary</h2>
                        <p class="text-xs text-sand-500">Updates live as you fill in the form.</p>
                    </div>

                    <dl class="flex flex-col gap-2.5 border-t border-sand-200 pt-4 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-sand-500">Visit Type</dt>
                            <dd class="font-semibold text-sand-900" x-text="visitType === 'Daytour' ? '☀️ Daytour' : '🌙 Overnight'"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-sand-500">Local Guests</dt>
                            <dd class="font-semibold text-sand-900" x-text="localTotal"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-sand-500">International Guests</dt>
                            <dd class="font-semibold text-sand-900" x-text="foreignTotal"></dd>
                        </div>
                    </dl>

                    <div class="rounded-md border-2 border-primary-700 bg-primary-100/40 px-4 py-3">
                        <p class="text-[10px] font-semibold tracking-wide text-primary-700 uppercase">Total Group Size</p>
                        <p class="mt-1 font-display text-3xl font-extrabold text-primary-900">
                            <span x-text="totalPeople"></span> <span class="text-sm font-semibold text-sand-500">People</span>
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="flex items-center justify-center gap-2 rounded-md bg-primary-700 px-5 py-3.5 text-sm font-semibold text-sand-0 shadow-md transition-colors hover:bg-primary-900 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <i class="ti" :class="submitting ? 'ti-loader-2 animate-spin' : 'ti-clipboard-check'" aria-hidden="true"></i>
                        <span x-text="submitting ? 'Submitting…' : 'Submit Registration'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
