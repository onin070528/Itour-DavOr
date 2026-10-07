{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Shared Audit Logs / Activity Log page — security and operation tabs, filters, table and
    export.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props([
    'role',
    'tab',
    'filters',
    'rows',
    'securitySummary' => null,
    'operationSummary' => null,
    'municipalities' => null,
    'subtitle',
    'baseRouteName',
    'exportRouteName' => null,
    'securityTabLabel' => 'Security Logs',
    'operationTabLabel' => 'Operation Logs',
])

@php
    use App\Enums\UserRole;
    use App\Models\OperationLog;
    use App\Models\SecurityLog;
    use Illuminate\Support\Str;

    $isEstablishment = $role === UserRole::Establishment;
    $isPto = $role === UserRole::PtoAdministrator;
    $showIpAndBrowser = ! $isEstablishment;
    $showSummary = ! $isEstablishment;
    $showExport = ! $isEstablishment && $exportRouteName;

    // Every control that changes the table (tabs, filters, pagination) must
    // carry the rest of the current query string forward, and municipality
    // scoping never comes from the request — it's simply never rendered as
    // a control for non-PTO roles, so there's nothing to tamper with.
    $tabQuery = fn (string $target) => route($baseRouteName, array_filter(['tab' => $target]));
    $activeTone = 'border-primary-700 text-primary-700';
    $inactiveTone = 'border-transparent text-sand-500 hover:text-sand-800';
@endphp

<x-dashboard.page-header :title="'Audit Logs'" :description="$subtitle">
    @if ($showExport)
        <x-slot:actions>
            <a
                href="{{ route($exportRouteName, array_merge($filters, ['tab' => $tab])) }}"
                class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300"
            >
                <i class="ti ti-file-type-xls" aria-hidden="true"></i>
                Export Excel
            </a>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
            >
                <i class="ti ti-file-type-pdf" aria-hidden="true"></i>
                Export PDF
            </button>
        </x-slot:actions>
    @endif
</x-dashboard.page-header>

{{-- Tabs are plain links, not the app's usual JS-toggled [data-tabs] --
     each tab is its own server-paginated dataset, so switching tabs has to
     be a real navigation, not a client-side panel swap. Styled identically
     to the JS component's active state (border-primary-700/text-primary-700)
     so it still reads as the same tab pattern used elsewhere. --}}
<div class="mt-6 flex gap-1 border-b border-sand-200" role="tablist">
    <a
        href="{{ $tabQuery('security') }}"
        role="tab"
        aria-selected="{{ $tab === 'security' ? 'true' : 'false' }}"
        class="-mb-px border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors {{ $tab === 'security' ? $activeTone : $inactiveTone }}"
    >
        {{ $securityTabLabel }}
    </a>
    <a
        href="{{ $tabQuery('operation') }}"
        role="tab"
        aria-selected="{{ $tab === 'operation' ? 'true' : 'false' }}"
        class="-mb-px border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors {{ $tab === 'operation' ? $activeTone : $inactiveTone }}"
    >
        {{ $operationTabLabel }}
    </a>
</div>

@if ($showSummary)
    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        @if ($tab === 'security')
            <x-dashboard.kpi-card label="Total events (last 30 days)" :value="number_format($securitySummary['total'])" />
            <x-dashboard.kpi-card label="Failed logins" :value="number_format($securitySummary['failed_logins'])" tone="warning" />
            <x-dashboard.kpi-card label="Access denied" :value="number_format($securitySummary['access_denied'])" tone="danger" />
        @else
            <x-dashboard.kpi-card label="Total actions (last 30 days)" :value="number_format($operationSummary['total'])" />
            <x-dashboard.kpi-card label="Approvals" :value="number_format($operationSummary['approvals'])" tone="success" />
            <x-dashboard.kpi-card label="Returned / Rejected" :value="number_format($operationSummary['returned_or_rejected'])" tone="warning" />
        @endif
    </div>
@endif

<form method="GET" action="{{ route($baseRouteName) }}" class="mt-5 flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
        <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
        <input
            type="search"
            name="search"
            value="{{ $filters['search'] ?? '' }}"
            placeholder="Search by user name, email, or record..."
            class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none"
        >
    </div>

    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">

    @if ($tab === 'security')
        <select name="event_type" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
            <option value="">All Events</option>
            @foreach (SecurityLog::EVENT_TYPES as $type)
                <option value="{{ $type }}" @selected(($filters['event_type'] ?? null) === $type)>{{ Str::headline($type) }}</option>
            @endforeach
        </select>
    @else
        <select name="action" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
            <option value="">All Actions</option>
            @foreach (OperationLog::ACTIONS as $action)
                <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ Str::headline($action) }}</option>
            @endforeach
        </select>
    @endif

    @if ($isPto)
        <select name="municipality_id" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
            <option value="">All Municipalities</option>
            @foreach ($municipalities as $municipality)
                <option value="{{ $municipality->mun_id }}" @selected((string) ($filters['municipality_id'] ?? '') === (string) $municipality->mun_id)>{{ $municipality->mun_name }}</option>
            @endforeach
        </select>
    @endif

    <button type="submit" class="rounded-sm bg-primary-700 px-3 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
        Filter
    </button>
    <a href="{{ $tabQuery($tab) }}" class="rounded-sm border border-sand-300 px-3 py-2.5 text-center text-sm font-semibold text-sand-700 hover:border-primary-300">
        Reset
    </a>
</form>

@if ($rows->isEmpty())
    <x-dashboard.empty-state
        class="mt-5"
        icon="ti-shield-search"
        title="No log entries match your filters."
    >
        <x-slot:action>
            <a href="{{ $tabQuery($tab) }}" class="rounded-sm border border-sand-300 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                Reset filters
            </a>
        </x-slot:action>
    </x-dashboard.empty-state>
@else
    <div class="mt-5 overflow-x-auto rounded-md border border-sand-200 bg-sand-0">
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3">Date &amp; Time</th>
                    @if ($tab === 'security')
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Municipality</th>
                        @if ($showIpAndBrowser)
                            <th class="px-4 py-3">IP Address</th>
                        @endif
                    @else
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Record</th>
                        <th class="px-4 py-3">Municipality</th>
                        <th class="px-4 py-3">Establishment</th>
                    @endif
                    <th class="px-4 py-3">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach ($rows as $row)
                    @if ($tab === 'security')
                        @php
                            $details = [
                                'event' => Str::headline($row->sec_event_type),
                                'user' => $row->user?->usr_name ?? $row->sec_attempted_email ?? 'Unknown',
                                'time' => $row->sec_created_at->timezone('Asia/Manila')->format('F j, Y g:i A'),
                                'details' => $row->sec_details ? json_encode($row->sec_details) : null,
                            ];
                            if ($showIpAndBrowser) {
                                $details['ip'] = $row->sec_ip_address;
                                $details['browser'] = $row->sec_user_agent;
                            }
                        @endphp
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sand-700">{{ $row->sec_created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $row->user?->usr_name ?? $row->sec_attempted_email ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.status-badge :tone="SecurityLog::badgeTone($row->sec_event_type)">{{ Str::headline($row->sec_event_type) }}</x-dashboard.status-badge>
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ $row->municipality?->mun_name ?? '—' }}</td>
                            @if ($showIpAndBrowser)
                                <td class="px-4 py-3 text-sand-700">{{ $row->sec_ip_address ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-3 text-right">
                                <button type="button" data-details-trigger="audit-log-details-modal" data-details="{{ json_encode($details) }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                    View
                                </button>
                            </td>
                        </tr>
                    @else
                        @php
                            $details = [
                                'action' => Str::headline($row->opl_action),
                                'user' => $row->user ? "{$row->user->usr_name} ({$row->opl_user_role})" : $row->opl_user_role,
                                'record' => Str::headline($row->opl_entity_type).' #'.$row->opl_entity_id,
                                'reason' => $row->opl_reason,
                                'old_values' => $row->opl_old_values,
                                'new_values' => $row->opl_new_values,
                            ];
                        @endphp
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sand-700">{{ $row->opl_created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $row->user?->usr_name ?? '—' }} <span class="text-xs text-sand-500">({{ $row->opl_user_role }})</span></td>
                            <td class="px-4 py-3">
                                <x-dashboard.status-badge :tone="OperationLog::badgeTone($row->opl_action)">{{ Str::headline($row->opl_action) }}</x-dashboard.status-badge>
                            </td>
                            <td class="px-4 py-3 text-sand-700">{{ Str::headline($row->opl_entity_type) }} #{{ $row->opl_entity_id ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $row->municipality?->mun_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $row->establishment?->lst_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" data-details-trigger="audit-log-details-modal" data-details="{{ json_encode($details) }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                    View
                                </button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($rows->hasPages())
    {{-- Hand-rolled to match this app's own pagination look (the same
         classes resources/js/dashboard.js's renderPagination() generates
         for client-side tables) rather than Laravel's default Tailwind
         pagination view, which ships different, unrelated colors. --}}
    <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-xs text-sand-500">Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</p>
        <div class="flex items-center gap-1">
            @php
                // A 10,000-row export cap implies up to 500 pages at 20/page
                // — a bounded window (current ±2, plus the first/last page)
                // keeps the pager usable instead of rendering every page number.
                $windowStart = max(1, $rows->currentPage() - 2);
                $windowEnd = min($rows->lastPage(), $rows->currentPage() + 2);
            @endphp
            <a
                href="{{ $rows->previousPageUrl() ?? '#' }}"
                @class(['rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors', 'pointer-events-none text-sand-300' => ! $rows->previousPageUrl(), 'text-sand-700 hover:bg-sand-100' => $rows->previousPageUrl()])
            >Previous</a>
            @if ($windowStart > 1)
                <a href="{{ $rows->url(1) }}" class="rounded-sm px-3 py-1.5 text-xs font-semibold text-sand-700 hover:bg-sand-100">1</a>
                @if ($windowStart > 2)
                    <span class="px-1 text-xs text-sand-400">&hellip;</span>
                @endif
            @endif
            @for ($page = $windowStart; $page <= $windowEnd; $page++)
                <a
                    href="{{ $rows->url($page) }}"
                    @class(['rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors', 'bg-primary-700 text-white' => $page === $rows->currentPage(), 'text-sand-700 hover:bg-sand-100' => $page !== $rows->currentPage()])
                >{{ $page }}</a>
            @endfor
            @if ($windowEnd < $rows->lastPage())
                @if ($windowEnd < $rows->lastPage() - 1)
                    <span class="px-1 text-xs text-sand-400">&hellip;</span>
                @endif
                <a href="{{ $rows->url($rows->lastPage()) }}" class="rounded-sm px-3 py-1.5 text-xs font-semibold text-sand-700 hover:bg-sand-100">{{ $rows->lastPage() }}</a>
            @endif
            <a
                href="{{ $rows->nextPageUrl() ?? '#' }}"
                @class(['rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors', 'pointer-events-none text-sand-300' => ! $rows->nextPageUrl(), 'text-sand-700 hover:bg-sand-100' => $rows->nextPageUrl()])
            >Next</a>
        </div>
    </div>
@elseif ($rows->total())
    <p class="mt-3 text-xs text-sand-500">Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</p>
@endif

<x-dashboard.modal id="audit-log-details-modal" title="Log Details" max-width="max-w-lg">
    <dl class="flex flex-col gap-3 text-sm">
        <div data-field-row>
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Event / Action</dt>
            <dd data-field="event" class="mt-0.5 text-sand-900"></dd>
            <dd data-field="action" class="mt-0.5 text-sand-900"></dd>
        </div>
        <div data-field-row>
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">User</dt>
            <dd data-field="user" class="mt-0.5 text-sand-900"></dd>
        </div>
        <div data-field-row>
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Record</dt>
            <dd data-field="record" class="mt-0.5 text-sand-900"></dd>
        </div>
        <div data-field-row>
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Time (Asia/Manila)</dt>
            <dd data-field="time" class="mt-0.5 text-sand-900"></dd>
        </div>
        @if ($showIpAndBrowser)
            <div data-field-row>
                <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">IP Address</dt>
                <dd data-field="ip" class="mt-0.5 text-sand-900"></dd>
            </div>
            <div data-field-row>
                <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Browser</dt>
                <dd data-field="browser" class="mt-0.5 text-sand-900 break-all"></dd>
            </div>
        @endif
        <div data-field-row>
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Reason</dt>
            <dd data-field="reason" class="mt-0.5 text-sand-900"></dd>
        </div>
        <div data-field-row>
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Details</dt>
            <dd data-field="details" class="mt-0.5 text-sand-900 break-all"></dd>
        </div>
        <div data-diff-section class="hidden">
            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Before / After</dt>
            <dd data-diff-container class="mt-1"></dd>
        </div>
    </dl>
</x-dashboard.modal>
