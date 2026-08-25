<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Reports"
        description="Reports limited to {{ $establishmentName }}."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($reportTypes as $type)
            <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-5">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-primary-100 text-primary-700">
                    <i class="ti {{ $type['icon'] }} text-lg" aria-hidden="true"></i>
                </span>
                <div>
                    <p class="font-display text-sm font-bold text-sand-900">{{ $type['label'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-sand-600">{{ $type['description'] }}</p>
                </div>
                <button
                    type="button"
                    data-modal-open="generate-report-modal"
                    data-report-type="{{ $type['label'] }}"
                    class="mt-auto inline-flex items-center justify-center gap-2 rounded-sm border border-sand-300 px-3.5 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300"
                >
                    <i class="ti ti-file-report" aria-hidden="true"></i>
                    Generate Report
                </button>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Recent Reports</h2>

        @if (count($history))
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs font-semibold text-sand-500 uppercase">
                            <th class="py-2 pr-2">Report</th>
                            <th class="py-2 pr-2">Range</th>
                            <th class="py-2 pr-2">Generated</th>
                            <th class="py-2 pr-2">Status</th>
                            <th class="py-2 pr-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($history as $report)
                            <tr>
                                <td class="py-2.5 pr-2 font-medium text-sand-900">{{ $report['name'] }}</td>
                                <td class="py-2.5 pr-2 text-sand-600">{{ $report['range'] }}</td>
                                <td class="py-2.5 pr-2 text-sand-600">{{ \Illuminate\Support\Carbon::parse($report['generatedAt'])->format('M j, Y') }}</td>
                                <td class="py-2.5 pr-2"><x-dashboard.status-badge tone="success">{{ $report['status'] }}</x-dashboard.status-badge></td>
                                <td class="py-2.5 pr-2 text-right">
                                    <button type="button" data-toast-trigger data-toast-message="Downloading {{ $report['name'] }}..." class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                        <i class="ti ti-download" aria-hidden="true"></i> Download
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-3 text-sm text-sand-500">No reports generated yet.</p>
        @endif
    </div>

    <x-dashboard.modal id="generate-report-modal" title="Generate Report">
        <form class="flex flex-col gap-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Report Type</label>
                <select name="type" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    @foreach ($reportTypes as $type)
                        <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">From</label>
                    <input name="from" type="date" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">To</label>
                    <input name="to" type="date" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Tourist Classification</label>
                <select name="classification" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    <option value="">All Classifications</option>
                    <option>Local (Same Province)</option>
                    <option>Domestic (Other Province)</option>
                    <option>Foreign</option>
                </select>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-700 hover:border-sand-400">Cancel</button>
            <button type="button" data-modal-close data-toast-message="Report generation started. It will appear in Recent Reports shortly." class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Generate Report
            </button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
