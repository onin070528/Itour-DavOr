<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Record Arrival"
        description="Log a guest arrival at {{ $establishmentName }}."
    />

    <div id="arrival-wizard" class="mt-6 max-w-2xl">
        {{-- Stepper --}}
        <ol class="mb-6 flex items-center gap-4 text-sm font-semibold">
            @foreach (['Enter Information', 'Review', 'Submitted'] as $i => $label)
                <li data-stepper-item class="flex items-center gap-2 text-sand-400">
                    <span data-stepper-dot class="flex h-6 w-6 items-center justify-center rounded-full bg-sand-300 text-xs text-sand-0">{{ $i + 1 }}</span>
                    {{ $label }}
                    @if (! $loop->last)
                        <i class="ti ti-chevron-right text-sand-300" aria-hidden="true"></i>
                    @endif
                </li>
            @endforeach
        </ol>

        <div class="rounded-md border border-sand-200 bg-sand-0 p-6">
            {{-- Step 1: Enter Information --}}
            <div data-step="enter">
                <form class="flex flex-col gap-4" onsubmit="return false">
                    <div data-field>
                        <label for="arrival-date" class="mb-1 block text-xs font-semibold text-sand-700">Date</label>
                        <input id="arrival-date" name="date" type="date" required value="{{ now()->toDateString() }}" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>

                    <div data-field>
                        <label for="visitor-name" class="mb-1 block text-xs font-semibold text-sand-700">Visitor Name <span class="font-normal text-sand-500">(optional)</span></label>
                        <input id="visitor-name" name="visitorName" type="text" placeholder="e.g. Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div data-field>
                            <label for="gender" class="mb-1 block text-xs font-semibold text-sand-700">Gender</label>
                            <select id="gender" name="gender" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                                <option value="">Select gender</option>
                                <option>Male</option>
                                <option>Female</option>
                            </select>
                        </div>

                        <div data-field>
                            <label for="classification" class="mb-1 block text-xs font-semibold text-sand-700">Tourist Classification</label>
                            <select id="classification" name="classification" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                                <option value="">Select classification</option>
                                <option>Local (Same Province)</option>
                                <option>Domestic (Other Province)</option>
                                <option>Foreign</option>
                            </select>
                        </div>
                    </div>

                    <div data-field>
                        <label for="remarks" class="mb-1 block text-xs font-semibold text-sand-700">Remarks <span class="font-normal text-sand-500">(optional)</span></label>
                        <textarea id="remarks" name="remarks" rows="2" placeholder="e.g. Group of 3, return guest..." class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                    </div>
                </form>

                <div class="mt-5 flex justify-end border-t border-sand-200 pt-5">
                    <button type="button" data-step-next class="rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        Review Information
                    </button>
                </div>
            </div>

            {{-- Step 2: Review --}}
            <div data-step="review" class="hidden">
                <h2 class="font-display text-sm font-bold text-sand-900">Review your entry</h2>
                <p class="text-xs text-sand-500">Make sure everything is correct before submitting.</p>

                <div data-review-summary class="mt-4"></div>

                <div class="mt-5 flex justify-between border-t border-sand-200 pt-5">
                    <button type="button" data-step-back class="rounded-sm border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                        <i class="ti ti-arrow-left" aria-hidden="true"></i>
                        Back
                    </button>
                    <button type="button" data-step-submit class="rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        Confirm & Submit
                    </button>
                </div>
            </div>

            {{-- Step 3: Success --}}
            <div data-step="success" class="hidden py-6 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success-bg text-success">
                    <i class="ti ti-circle-check text-3xl" aria-hidden="true"></i>
                </span>
                <p class="mt-4 font-display text-base font-bold text-sand-900">Submission Successful</p>
                <p class="mt-1 text-sm text-sand-600">The arrival record has been logged for {{ $establishmentName }}.</p>

                <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" data-step-reset class="rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        Record Another Arrival
                    </button>
                    <a href="{{ route('establishment.arrivals.index') }}" class="rounded-sm border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                        View Arrival Records
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
