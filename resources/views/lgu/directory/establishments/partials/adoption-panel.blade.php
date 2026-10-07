{{--
    iTOUR adoption on the details page: reporting method, the establishment's one
    account, and QR. Switching the method is the only way an account is created
    (Lgu\EstablishmentAdoptionController). Expects $listing, $blnCanManageQr.
--}}
@php
    $objReportingMethod = $listing->reportingMethod();
    $objAccount = $listing->establishmentUser;
    $blnHasAccount = $objAccount !== null;
@endphp

<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">iTOUR adoption</h2>

    <dl class="mt-4 flex flex-col gap-4">
        <div>
            <dt class="detail-term">Reporting method</dt>
            <dd class="detail-value inline-flex items-center gap-1.5">
                <i class="ti {{ $objReportingMethod->icon() }} text-sand-500" aria-hidden="true"></i>
                {{ $objReportingMethod->label() }}
            </dd>
            <p class="form-hint">
                {{ $objReportingMethod->isOnline()
                    ? 'Records arrivals and submits monthly reports in iTOUR.'
                    : 'Submits paper reports; your office encodes them in Tourism Reports.' }}
            </p>
        </div>

        <div>
            <dt class="detail-term">Establishment account</dt>
            <dd class="detail-value">
                @if ($blnHasAccount)
                    {{ $listing->accountStatusLabel() }} · {{ $objAccount->lst_email }}
                @else
                    None
                @endif
            </dd>
        </div>

        <div>
            <dt class="detail-term">QR code</dt>
            <dd class="mt-1"><x-dashboard.qr-cell :listing="$listing" /></dd>
        </div>
    </dl>

    <div class="mt-5 border-t border-sand-200 pt-4">
        @if ($objReportingMethod->isOnline())
            <button type="button" data-modal-open="switch-to-manual-modal" class="btn-secondary w-full justify-center">
                <i class="ti ti-file-text" aria-hidden="true"></i>
                Switch to Manual/Paper
            </button>
        @else
            <button type="button" data-modal-open="switch-to-online-modal" class="btn-primary w-full justify-center">
                <i class="ti ti-device-laptop" aria-hidden="true"></i>
                {{ $blnHasAccount ? 'Switch to Online iTOUR' : 'Activate Online iTOUR account' }}
            </button>
        @endif
    </div>

    <x-dashboard.qr-modal :listing="$listing" :can-manage="$blnCanManageQr" />
</section>

@if ($objReportingMethod->isOnline())
    <x-dashboard.modal id="switch-to-manual-modal" title="Switch to Manual/Paper reporting?">
        <form id="switch-to-manual-form" method="POST" action="{{ route('lgu.directory.establishments.switchToManual', $listing) }}">
            @csrf
            @method('PATCH')
        </form>
        <ul class="flex list-disc flex-col gap-1.5 pl-5 text-sm text-sand-700">
            <li>{{ $listing->lst_name }} will submit paper reports, and your office will encode them.</li>
            @if ($objAccount?->lst_status === \App\Models\Listing::ACCOUNT_STATUS_ACTIVE)
                <li>Its account ({{ $objAccount->lst_email }}) will be <strong>suspended</strong>, not deleted. It can be reactivated later.</li>
            @endif
            <li>QR check-in stops. Recorded arrivals and reports are kept.</li>
        </ul>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">Cancel</button>
            <button type="submit" form="switch-to-manual-form" class="btn-primary">Switch to Manual/Paper</button>
        </x-slot:footer>
    </x-dashboard.modal>
@else
    <x-dashboard.modal id="switch-to-online-modal" title="Switch to Online iTOUR reporting" :open="$errors->hasAny(['account_name', 'account_email'])">
        <form id="switch-to-online-form" method="POST" action="{{ route('lgu.directory.establishments.switchToOnline', $listing) }}" class="flex flex-col gap-4">
            @csrf

            @if ($blnHasAccount)
                <p class="text-sm text-sand-700">{{ $listing->lst_name }} already has an account ({{ $objAccount->lst_email }}). It will be reactivated with its current password — no new account is created.</p>
            @else
                <p class="text-sm text-sand-700">An establishment account will be created for {{ $listing->lst_name }}, in {{ $listing->lst_municipality }}. A temporary password is shown once after saving and sent by email; it must be changed at first sign-in.</p>

                <div>
                    <label for="account-name" class="form-label">Account holder's name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="account-name" name="account_name" type="text" required maxlength="255" value="{{ old('account_name', $listing->lst_owner_name) }}" class="form-input">
                    @error('account_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="account-email" class="form-label">Sign-in email <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="account-email" name="account_email" type="email" required maxlength="255" value="{{ old('account_email', $listing->lst_email) }}" class="form-input">
                    <p class="form-hint">Must not already be used by another iTOUR account.</p>
                    @error('account_email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <p class="text-xs text-sand-500">QR check-in becomes available once the account is active and the establishment's category allows QR check-in.</p>
        </form>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">Cancel</button>
            <button type="submit" form="switch-to-online-form" class="btn-primary">{{ $blnHasAccount ? 'Switch and reactivate account' : 'Create account and switch' }}</button>
        </x-slot:footer>
    </x-dashboard.modal>
@endif
