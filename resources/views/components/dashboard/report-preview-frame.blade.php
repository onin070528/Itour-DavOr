{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: The "Report Preview" panel used on report pages — the official
    A4 document shown at a readable size, with clear Print and Download PDF
    actions above it. Print prints the document itself (not the dashboard
    around it).
    Props: frameId, previewUrl, pdfUrl (optional)
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['frameId', 'previewUrl', 'pdfUrl' => null])

<div class="mt-6 rounded-md border border-sand-200 bg-sand-100 p-2 sm:p-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-sand-600">The official report document, as it will be printed.</p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="document.getElementById('{{ $frameId }}').contentWindow.print()" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-printer" aria-hidden="true"></i>
                Print
            </button>
            @if ($pdfUrl)
                <a href="{{ $pdfUrl }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-download" aria-hidden="true"></i>
                    Download PDF
                </a>
            @endif
        </div>
    </div>
    <iframe id="{{ $frameId }}" src="{{ $previewUrl }}" title="Report Preview" class="h-[80vh] w-full rounded-sm border border-sand-200 bg-sand-0"></iframe>
</div>
