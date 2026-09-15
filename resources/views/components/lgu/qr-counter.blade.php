@props(['name', 'label', 'caption' => null])

{{-- One +/- counter block, wired up generically in initEstablishmentQrForm()
     (resources/js/app.js) via the data-counter attribute. --}}
<div data-counter="{{ $name }}" class="rounded-md border border-sand-200 bg-sand-50 p-3 text-center">
    <p class="text-sm font-semibold text-sand-900">{{ $label }}</p>
    @if ($caption)
        <p class="text-[11px] text-sand-500">{{ $caption }}</p>
    @endif

    <div class="mt-2 flex items-center justify-center gap-2">
        <button
            type="button"
            data-counter-decrement
            class="flex h-8 w-8 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
            aria-label="Decrease {{ $label }}"
        >
            <i class="ti ti-minus text-sm" aria-hidden="true"></i>
        </button>
        <span data-counter-value class="flex h-8 min-w-10 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sm font-bold text-sand-900">0</span>
        <button
            type="button"
            data-counter-increment
            class="flex h-8 w-8 items-center justify-center rounded-sm border border-sand-300 bg-sand-0 text-sand-700 transition-colors hover:border-primary-300 hover:text-primary-700"
            aria-label="Increase {{ $label }}"
        >
            <i class="ti ti-plus text-sm" aria-hidden="true"></i>
        </button>
    </div>
</div>
