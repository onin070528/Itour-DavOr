@props(['id', 'title', 'maxWidth' => 'max-w-lg'])

<div id="{{ $id }}" data-modal class="fixed inset-0 z-50 hidden">
    <div data-modal-backdrop class="flex min-h-full items-center justify-center bg-sand-900/50 p-4">
        <div class="w-full {{ $maxWidth }} rounded-lg bg-sand-0 shadow-md">
            <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
                <h2 class="font-display text-base font-bold text-sand-900">{{ $title }}</h2>
                <button type="button" data-modal-close class="text-sand-500 hover:text-sand-800" aria-label="Close">
                    <i class="ti ti-x text-lg" aria-hidden="true"></i>
                </button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex items-center justify-end gap-2 border-t border-sand-200 px-5 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
