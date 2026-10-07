@props(['for' => 'password', 'withTemporaryPasswordRule' => false])

{{-- Live checklist shown under a new-password field; resources/js/app.js
     (initPasswordChecklist) ticks each rule as the user types. The rules
     mirror Password::defaults() in App\Providers\AppServiceProvider — change
     both together. A visual guide only: the server validation decides.
     "Different from your temporary password" can't be checked in the
     browser without sending the temporary password to it, so it stays a
     plain note that the server checks on save. --}}
<div id="{{ $for }}-requirements" data-password-checklist="{{ $for }}" {{ $attributes->merge(['class' => 'mt-2']) }}>
    <p class="text-sm font-medium text-sand-700">Password requirements:</p>
    <ul class="mt-1 space-y-0.5 text-sm">
        @foreach (['length' => 'At least 12 characters', 'letter' => 'At least 1 letter', 'number' => 'At least 1 number', 'symbol' => 'At least 1 special character'] as $rule => $label)
            <li data-rule="{{ $rule }}" data-met="false" class="flex items-center gap-1.5 text-sand-600 transition-colors data-[met=true]:text-primary-700">
                <i class="ti ti-square" data-icon-unmet aria-hidden="true"></i>
                <i class="ti ti-square-check hidden" data-icon-met aria-hidden="true"></i>
                <span>{{ $label }}</span>
                <span class="sr-only" data-rule-state>(not met yet)</span>
            </li>
        @endforeach

        @if ($withTemporaryPasswordRule)
            <li data-temporary-password-rule class="flex items-center gap-1.5 text-sand-600">
                <i class="ti ti-square" aria-hidden="true"></i>
                <span>Different from your temporary password <span class="text-sand-500">(checked when you save)</span></span>
                <span class="sr-only">(not met yet)</span>
            </li>
        @endif
    </ul>
</div>
