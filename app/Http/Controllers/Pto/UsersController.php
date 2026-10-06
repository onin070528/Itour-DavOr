<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO-only user account management — create/edit PTO, LGU, and
 * Establishment accounts province-wide. New accounts are created with a
 * one-time temporary passphrase and a forced password change on first
 * login (see App\Services\UserAccountProvisioner). See Lgu\UsersController
 * for the LGU-scoped equivalent (LGU creates Establishment accounts within
 * its own municipality only, via a separate "register a new establishment"
 * flow — unaffected by this one).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\UserRole;
use App\Events\UserAccountStatusChanged;
use App\Events\UserRoleChanged;
use App\Http\Requests\StoreUserRequest;
use App\Mail\WelcomeAccountCreated;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\UserAccountProvisioner;
use App\Support\PtoMockData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsersController extends PtoController
{
    /**
     * User Management: PTO, LGU, and Establishment accounts, province-wide.
     */
    public function index(Request $objRequest): View
    {
        return $this->renderPto($objRequest, 'pto.users', 'users', 'User Management', [
            'users' => PtoMockData::users(),
            'municipalities' => Municipality::query()->orderBy('mun_name')->get(['mun_id', 'mun_name']),
        ]);
    }

    /**
     * Resolves the role/municipality/establishment fields into the real
     * account attributes, then hands off to UserAccountProvisioner for the
     * passphrase, the audit event, and the welcome email. The new
     * account's temporary passphrase is flashed once — read by the view
     * on this one redirect only, never persisted anywhere.
     */
    public function store(StoreUserRequest $objRequest, UserAccountProvisioner $objProvisioner): RedirectResponse
    {
        $objRole = UserRole::from($objRequest->validated('role'));

        [$strOrganizationName, $strOrganizationSubtitle, $intMunicipalityId, $intEstablishmentId] = match ($objRole) {
            UserRole::Establishment => $this->_resolveEstablishmentById((int) $objRequest->validated('establishment_id')),
            UserRole::Lgu => $this->_resolveMunicipalityById((int) $objRequest->validated('municipality_id')),
            UserRole::PtoAdministrator => [$objRequest->validated('name'), $objRequest->validated('name'), null, null],
        };

        try {
            $arrResult = $objProvisioner->provision($objRequest->user(), [
                'usr_name' => $objRequest->validated('name'),
                'usr_email' => $objRequest->validated('email'),
                'usr_email_verified_at' => now(),
                'usr_role' => $objRole,
                'usr_organization_name' => $strOrganizationName,
                'usr_organization_subtitle' => $strOrganizationSubtitle,
                'mun_id' => $intMunicipalityId,
                'lst_id' => $intEstablishmentId,
                'usr_status' => 'Active',
                'usr_created_by' => $objRequest->user()->usr_id,
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to create user account.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('accountCreated', [
            'userId' => $arrResult['user']->usr_id,
            'name' => $arrResult['user']->usr_name,
            'role' => $objRole->title(),
            'municipality' => $arrResult['user']->municipality?->mun_name,
            'passphrase' => $arrResult['passphrase'],
            'emailSent' => $arrResult['emailSent'],
        ]);
    }

    public function update(Request $objRequest, User $user): RedirectResponse
    {
        $arrData = $this->_validatedForUpdate($objRequest, $user);

        $objFromRole = $user->usr_role;

        try {
            $user->update([
                'usr_name' => $arrData['name'],
                'usr_email' => $arrData['email'],
                'usr_role' => $arrData['role'],
                'usr_organization_name' => $arrData['organization_name'],
                'usr_organization_subtitle' => $arrData['organization_subtitle'],
                'mun_id' => $arrData['municipality_id'],
                'lst_id' => $arrData['establishment_id'],
                'usr_phone' => $arrData['phone'],
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update user account.', ['exception' => $objException, 'user_id' => $user->usr_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        if ($objFromRole !== $user->usr_role) {
            event(new UserRoleChanged($objRequest->user(), $user, $objFromRole, $user->usr_role));
        }

        return back()->with('toast', 'User account saved.');
    }

    public function toggleStatus(Request $objRequest, User $user): RedirectResponse
    {
        // Nobody — including a PTO administrator — can change their own status.
        abort_if($user->usr_id === $objRequest->user()->usr_id, 403, 'You cannot change the status of your own account.');

        $strNext = $user->usr_status === 'Active' ? 'Inactive' : 'Active';

        try {
            $user->update(['usr_status' => $strNext]);
        } catch (\Throwable $objException) {
            Log::error('Failed to toggle user account status.', ['exception' => $objException, 'user_id' => $user->usr_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        event(new UserAccountStatusChanged($objRequest->user(), $user, $strNext));

        $strVerb = $strNext === 'Active' ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->usr_name}'s account was {$strVerb}.");
    }

    /**
     * Retries the welcome email from the confirmation panel after an
     * automatic send failed. The passphrase only ever exists in that
     * panel's own DOM (never persisted) — this is the one additional
     * request it travels through, same-origin and CSRF-protected, to
     * reach Mail once more.
     */
    public function resendWelcomeEmail(Request $objRequest): JsonResponse
    {
        $arrData = $objRequest->validate([
            'user_id' => ['required', 'integer', 'exists:tbl_users,usr_id'],
            'passphrase' => ['required', 'string'],
        ]);

        $objUser = User::query()->findOrFail($arrData['user_id']);

        try {
            Mail::to($objUser->usr_email)->send(new WelcomeAccountCreated($objUser, $arrData['passphrase']));

            return response()->json(['sent' => true]);
        } catch (\Throwable $objException) {
            Log::error('Failed to resend the welcome email.', ['exception' => $objException, 'user_id' => $objUser->usr_id]);

            return response()->json(['sent' => false], 500);
        }
    }

    /**
     * AJAX: establishments in the given municipality with no linked user
     * account yet — feeds the Add User modal's Establishment dropdown.
     */
    public function availableEstablishments(Request $objRequest): JsonResponse
    {
        $arrData = $objRequest->validate(['municipality_id' => ['required', 'integer', 'exists:tbl_municipalities,mun_id']]);

        $objEstablishments = Listing::query()
            ->where('mun_id', $arrData['municipality_id'])
            ->where('lst_category', '!=', 'destinations')
            ->whereDoesntHave('establishmentUser')
            ->orderBy('lst_name')
            ->get(['lst_id as id', 'lst_name as name']);

        return response()->json(['establishments' => $objEstablishments]);
    }

    /**
     * Editing keeps the same "nobody is promoted to PTO from here"
     * safeguard as before, reading the role/municipality/establishment
     * fields directly now instead of the old free-text assignment field.
     *
     * @return array{name: string, email: string, role: UserRole, organization_name: string, organization_subtitle: string, municipality_id: ?int, establishment_id: ?int, phone: ?string}
     */
    private function _validatedForUpdate(Request $objRequest, User $objUser): array
    {
        $arrData = $objRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tbl_users', 'usr_email')->ignore($objUser)],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'municipality_id' => ['required_unless:role,'.UserRole::PtoAdministrator->value, 'nullable', 'integer', 'exists:tbl_municipalities,mun_id'],
            'establishment_id' => ['required_if:role,'.UserRole::Establishment->value, 'nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $objRole = UserRole::from($arrData['role']);

        if ($objRole === UserRole::PtoAdministrator && $objUser->usr_role !== UserRole::PtoAdministrator) {
            throw ValidationException::withMessages([
                'role' => 'PTO Administrator accounts cannot be granted from this page.',
            ]);
        }

        [$strOrganizationName, $strOrganizationSubtitle, $intMunicipalityId, $intEstablishmentId] = match ($objRole) {
            UserRole::Establishment => $this->_resolveEstablishmentById((int) $arrData['establishment_id'], $objUser),
            UserRole::Lgu => $this->_resolveMunicipalityById((int) $arrData['municipality_id']),
            UserRole::PtoAdministrator => [$arrData['name'], $arrData['name'], null, null],
        };

        return [
            'name' => $arrData['name'],
            'email' => $arrData['email'],
            'role' => $objRole,
            'organization_name' => $strOrganizationName,
            'organization_subtitle' => $strOrganizationSubtitle,
            'municipality_id' => $intMunicipalityId,
            'establishment_id' => $intEstablishmentId,
            'phone' => $arrData['phone'] ?? null,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: ?int, 3: int}
     */
    private function _resolveEstablishmentById(int $intEstablishmentId, ?User $objUser = null): array
    {
        $objListing = Listing::query()->where('lst_id', $intEstablishmentId)->where('lst_category', '!=', 'destinations')->first();

        if (! $objListing) {
            throw ValidationException::withMessages(['establishment_id' => 'That establishment could not be found.']);
        }

        $blnLinkedToAnotherUser = User::query()
            ->where('lst_id', $objListing->lst_id)
            ->when($objUser, fn ($objQuery) => $objQuery->whereKeyNot($objUser->usr_id))
            ->exists();

        if ($blnLinkedToAnotherUser) {
            throw ValidationException::withMessages(['establishment_id' => "\"{$objListing->lst_name}\" already has an account linked to it."]);
        }

        return [$objListing->lst_name, "{$objListing->lst_barangay}, {$objListing->lst_municipality}", $objListing->mun_id, $objListing->lst_id];
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: null}
     */
    private function _resolveMunicipalityById(int $intMunicipalityId): array
    {
        $objMunicipality = Municipality::query()->find($intMunicipalityId);

        if (! $objMunicipality) {
            throw ValidationException::withMessages(['municipality_id' => 'That municipality could not be found.']);
        }

        return [$objMunicipality->mun_name, $objMunicipality->mun_name, $objMunicipality->mun_id, null];
    }
}
