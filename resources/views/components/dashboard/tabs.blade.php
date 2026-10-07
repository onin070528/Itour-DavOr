{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Tab bar for the reporting modules (same look as the Audit Logs
    tabs). Two modes, chosen per tab:
      - in-page tab: give it a 'panel' key. It becomes a button that shows
        the matching [data-tab-panel] without leaving the page (handled by
        initTabs() in resources/js/dashboard.js). Pass `sync` (a query
        parameter name) to remember the open tab in the URL / filter forms.
      - link tab: give it an 'href' key instead (a normal page link).
    Props:
      tabs       — array<int, array{label: string, active: bool, panel?: string, href?: string, icon?: string}>
      panelHost  — optional CSS selector limiting which panels these tabs control
      sync       — optional query parameter name, e.g. 'tab'
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['tabs', 'panelHost' => null, 'sync' => null])

<div
    {{ $attributes->merge(['class' => 'mt-6 flex gap-1 overflow-x-auto border-b border-sand-200']) }}
    role="tablist"
    data-tabs
    @if ($panelHost) data-tab-panel-host="{{ $panelHost }}" @endif
    @if ($sync) data-tab-sync="{{ $sync }}" @endif
>
    @foreach ($tabs as $tab)
        @php
            $tabClasses = [
                '-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors',
                'border-primary-700 text-primary-700' => $tab['active'],
                'border-transparent text-sand-500 hover:text-sand-800' => ! $tab['active'],
            ];
        @endphp
        @isset($tab['panel'])
            <button type="button" data-tab-target="{{ $tab['panel'] }}" role="tab" aria-selected="{{ $tab['active'] ? 'true' : 'false' }}" @class($tabClasses)>
                @isset($tab['icon'])
                    <i class="ti {{ $tab['icon'] }}" aria-hidden="true"></i>
                @endisset
                {{ $tab['label'] }}
            </button>
        @else
            <a href="{{ $tab['href'] }}" role="tab" aria-selected="{{ $tab['active'] ? 'true' : 'false' }}" @class($tabClasses)>
                @isset($tab['icon'])
                    <i class="ti {{ $tab['icon'] }}" aria-hidden="true"></i>
                @endisset
                {{ $tab['label'] }}
            </a>
        @endisset
    @endforeach
</div>
