{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : One-time "Account Created" panel — shows a new account's temporary password once, with Copy and (if the welcome email failed) Send Welcome Email.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.

    Opened automatically by resources/js/user_account.js (load it on the page).
    The password only lives in this one flashed session value — it is never
    stored on the account or written to any log.

    Props:
      account    The flashed `accountCreated` array (userId, name, role, municipality, passphrase, emailSent).
      resendUrl  POST endpoint that resends the welcome email.
--}}
@props(['account', 'resendUrl'])

<x-dashboard.modal id="account-created-modal" title="Account Created">
    <div class="flex flex-col gap-3">
        <dl class="flex flex-col gap-2 text-sm">
            <div><dt class="detail-term">Name</dt><dd class="text-sand-800">{{ $account['name'] }}</dd></div>
            <div><dt class="detail-term">Role</dt><dd class="text-sand-800">{{ $account['role'] }}</dd></div>
            @if (! empty($account['municipality']))
                <div><dt class="detail-term">Municipality</dt><dd class="text-sand-800">{{ $account['municipality'] }}</dd></div>
            @endif
        </dl>

        <div class="rounded-md border border-sand-300 bg-sand-50 p-3">
            <p class="text-xs font-semibold text-sand-700">Temporary Password</p>
            <div class="mt-1.5 flex items-center justify-between gap-2">
                <span id="account-created-passphrase" class="font-mono text-sm text-sand-900">{{ $account['passphrase'] }}</span>
                <button type="button" id="account-created-copy" class="btn-small shrink-0 bg-sand-0">Copy</button>
            </div>
        </div>

        <p class="text-xs text-sand-500">This password will not be shown again. The account holder must change it the first time they sign in.</p>

        <p id="account-created-email-status" @class(['rounded-sm px-3 py-2 text-xs', 'bg-warning-bg text-warning' => ! $account['emailSent'], 'hidden' => $account['emailSent']])>
            Email could not be sent. Please give the temporary password to the account holder directly.
        </p>
    </div>

    <x-slot:footer>
        @unless ($account['emailSent'])
            <button type="button" id="account-created-resend" data-user-id="{{ $account['userId'] }}" data-resend-url="{{ $resendUrl }}" class="btn-secondary">
                Send Welcome Email
            </button>
        @endunless
        <button type="button" data-modal-close class="btn-primary">Done</button>
    </x-slot:footer>
</x-dashboard.modal>
