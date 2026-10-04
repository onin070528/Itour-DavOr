{{--
    Storage & Cache Usage notice — public pages only (see x-layouts.public),
    never inside the PTO/LGU/Establishment portals. Informational only: every
    cookie/cache item listed here is essential or functional, nothing is
    tracking/analytics. Shown/hidden/remembered by resources/js/storage_notice.js;
    this markup starts hidden so a page never flashes it before that script
    decides whether a stored choice already exists.
--}}
<div
    id="storage-notice"
    hidden
    class="fixed bottom-5 left-5 z-50 hidden w-[calc(100vw-2.5rem)] max-w-xs flex-col gap-3 rounded-2xl border border-sand-200 bg-sand-0 p-4 shadow-2xl sm:bottom-6 sm:left-6"
    role="dialog"
    aria-modal="false"
    aria-labelledby="storage-notice-heading"
>
    <div class="flex items-start gap-2.5">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
            <i class="ti ti-shield-check text-lg" aria-hidden="true"></i>
        </span>
        <p id="storage-notice-heading" class="pt-1 text-sm font-semibold leading-tight text-sand-900">Storage &amp; Cache Usage</p>
    </div>

    <ul class="list-disc space-y-1.5 pl-5 text-xs leading-relaxed text-sand-600">
        <li>Essential cookies keep you signed in and protect forms from tampering.</li>
        <li>A small local note remembers that you've seen this notice.</li>
        <li>Map, icon, and font requests (e.g. Mapbox) help pages display correctly.</li>
        <li>The Hotlines page is cached on your device so it still opens if you lose signal.</li>
    </ul>

    <p class="text-xs text-sand-500">
        <a href="{{ route('privacy') }}" class="font-semibold text-primary-700 hover:text-primary-900">Privacy Notice</a>
    </p>

    <div class="flex items-center justify-end gap-2">
        <button
            type="button"
            id="storage-notice-dismiss"
            class="rounded-sm px-3 py-1.5 text-xs font-semibold text-sand-600 transition-colors hover:bg-sand-100 hover:text-sand-800"
        >
            Dismiss
        </button>
        <button
            type="button"
            id="storage-notice-understand"
            class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 transition-colors hover:bg-primary-900"
        >
            I Understand
        </button>
    </div>
</div>
