{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Pager for public result lists (Explore directory, nearby lists) —
    the same look as the dashboard pagers, never Laravel's default view.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['paginator'])

@if ($paginator->hasPages())
    @php
        // A bounded window (current ±2, plus the first/last page) keeps the pager short on a phone.
        $intWindowStart = max(1, $paginator->currentPage() - 2);
        $intWindowEnd = min($paginator->lastPage(), $paginator->currentPage() + 2);
    @endphp
    <nav class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="Pagination">
        <p class="text-xs text-sand-500">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
        <div class="flex flex-wrap items-center justify-center gap-1">
            <a
                href="{{ $paginator->previousPageUrl() ?? '#' }}"
                @class(['rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors', 'pointer-events-none text-sand-300' => ! $paginator->previousPageUrl(), 'text-sand-700 hover:bg-sand-100' => $paginator->previousPageUrl()])
                @if (! $paginator->previousPageUrl()) aria-disabled="true" @endif
            >Previous</a>
            @if ($intWindowStart > 1)
                <a href="{{ $paginator->url(1) }}" class="rounded-sm px-3 py-1.5 text-xs font-semibold text-sand-700 hover:bg-sand-100">1</a>
                @if ($intWindowStart > 2)
                    <span class="px-1 text-xs text-sand-400">&hellip;</span>
                @endif
            @endif
            @for ($intPage = $intWindowStart; $intPage <= $intWindowEnd; $intPage++)
                <a
                    href="{{ $paginator->url($intPage) }}"
                    @class(['rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors', 'bg-primary-700 text-white' => $intPage === $paginator->currentPage(), 'text-sand-700 hover:bg-sand-100' => $intPage !== $paginator->currentPage()])
                    @if ($intPage === $paginator->currentPage()) aria-current="page" @endif
                >{{ $intPage }}</a>
            @endfor
            @if ($intWindowEnd < $paginator->lastPage())
                @if ($intWindowEnd < $paginator->lastPage() - 1)
                    <span class="px-1 text-xs text-sand-400">&hellip;</span>
                @endif
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="rounded-sm px-3 py-1.5 text-xs font-semibold text-sand-700 hover:bg-sand-100">{{ $paginator->lastPage() }}</a>
            @endif
            <a
                href="{{ $paginator->nextPageUrl() ?? '#' }}"
                @class(['rounded-sm px-3 py-1.5 text-xs font-semibold transition-colors', 'pointer-events-none text-sand-300' => ! $paginator->nextPageUrl(), 'text-sand-700 hover:bg-sand-100' => $paginator->nextPageUrl()])
                @if (! $paginator->nextPageUrl()) aria-disabled="true" @endif
            >Next</a>
        </div>
    </nav>
@elseif ($paginator->total() > 0)
    <p class="mt-6 text-xs text-sand-500">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
@endif
