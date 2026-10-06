{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Reporting workflow step tracker for the monthly report pages.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['steps'])

{{--
    $steps: array<int, array{label: string, state: 'done'|'current'|'pending'}>
    Shared between Lgu\MonthlyReportsController and Pto\MonthlyReportsController's
    "Tourism Reports" pages — both show the same Collect → Review & Verify →
    Consolidate → Submit to PTO progress for a municipality + period, PTO's
    just read-only.
--}}
<div class="flex items-center gap-2 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 p-4">
    @foreach ($steps as $i => $step)
        <div class="flex shrink-0 items-center gap-2">
            <span
                @class([
                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-success text-sand-0' => $step['state'] === 'done',
                    'bg-primary-700 text-sand-0' => $step['state'] === 'current',
                    'border border-sand-300 text-sand-400' => $step['state'] === 'pending',
                ])
            >
                @if ($step['state'] === 'done')
                    <i class="ti ti-check text-sm" aria-hidden="true"></i>
                @else
                    {{ $i + 1 }}
                @endif
            </span>
            <span @class(['text-sm font-semibold', 'text-sand-900' => $step['state'] !== 'pending', 'text-sand-400' => $step['state'] === 'pending'])>
                {{ $step['label'] }}
            </span>
        </div>
        @if (! $loop->last)
            <span class="h-px w-6 shrink-0 bg-sand-200 sm:w-10" aria-hidden="true"></span>
        @endif
    @endforeach
</div>
