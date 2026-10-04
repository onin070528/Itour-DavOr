<x-layouts.public title="Verify Report">
    <div class="mx-auto max-w-lg px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl">Verify an Official Report</h1>
        <p class="mt-2 text-sm text-sand-600">
            Enter the verification code printed on an iTOUR Official Report to confirm it's genuine. This only confirms the report exists and its status — it never shows arrival or visitor details.
        </p>

        <form method="GET" action="{{ route('reports.verify') }}" class="mt-6 flex gap-2">
            <input
                type="text"
                name="code"
                value="{{ $code }}"
                placeholder="e.g. ITOUR-A1B2C3D4E5"
                class="min-h-[44px] flex-1 rounded-sm border border-sand-500 px-3.5 py-2.5 text-base text-sand-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            >
            <button type="submit" class="min-h-[44px] rounded-sm bg-primary-700 px-5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Verify
            </button>
        </form>

        @if ($result)
            <div class="mt-6 rounded-md border border-success/30 bg-success-bg p-5">
                <p class="flex items-center gap-2 text-sm font-semibold text-success">
                    <i class="ti ti-circle-check" aria-hidden="true"></i>
                    This report is genuine.
                </p>
                <dl class="mt-4 flex flex-col gap-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-sand-600">Report No.</dt><dd class="font-semibold text-sand-900">{{ $result['reference_number'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-sand-600">Municipality</dt><dd class="font-semibold text-sand-900">{{ $result['municipality'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-sand-600">Period</dt><dd class="font-semibold text-sand-900">{{ $result['period_label'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-sand-600">Status</dt><dd class="font-semibold text-sand-900">{{ $result['status_label'] }}</dd></div>
                    @if ($result['verified_at'])
                        <div class="flex justify-between gap-3"><dt class="text-sand-600">Verified On</dt><dd class="font-semibold text-sand-900">{{ $result['verified_at'] }}</dd></div>
                    @endif
                </dl>
            </div>
        @elseif ($notFound)
            <div class="mt-6 flex items-center gap-2 rounded-md border border-danger/30 bg-danger-bg p-5 text-sm font-semibold text-danger">
                <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                No report matches that verification code.
            </div>
        @endif
    </div>
</x-layouts.public>
