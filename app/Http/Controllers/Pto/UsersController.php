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
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.users', 'users', 'User Management', [
            'users' => PtoMockData::users(),
            'municipalities' => Municipality::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Resolves the role/municipality/establishment fields into the real
     * account attributes, then hands off to UserAccountProvisioner for the
     * passphrase, the audit event, and the welcome email. The new
     * account's temporary passphrase is flashed once — read by the view
     * on this one redirect only, never persisted anywhere.
     */
    public function store(StoreUserRequest $request, UserAccountProvisioner $objProvisioner): RedirectResponse
    {
        $objRole = UserRole::from($request->validated('role'));

        [$strOrganizationName, $strOrganizationSubtitle, $intMunicipalityId, $intEstablishmentId] = match ($objRole) {
            UserRole::Establishment => $this->_resolveEstablishmentById((int) $request->validated('establishment_id')),
            UserRole::Lgu => $this->_resolveMunicipalityById((int) $request->validated('municipality_id')),
            UserRole::PtoAdministrator => [$request->validated('name'), $request->validated('name'), null, null],
        };

        try {
            $arrResult = $objProvisioner->provision($request->user(), [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'email_verified_at' => now(),
                'role' => $objRole,
                'organization_name' => $strOrganizationName,
                'organization_subtitle' => $strOrganizationSubtitle,
                'municipality_id' => $intMunicipalityId,
                'establishment_id' => $intEstablishmentId,
                'status' => 'Active',
                'created_by' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create user account.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('accountCreated', [
            'userId' => $arrResult['user']->id,
            'name' => $arrResult['user']->name,
            'role' => $objRole->title(),
            'municipality' => $arrResult['user']->municipality?->name,
            'passphrase' => $arrResult['passphrase'],
            'emailSent' => $arrResult['emailSent'],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('update', $user), 403);

        $data = $this->_validatedForUpdate($request, $user);

        $fromRole = $user->role;

        try {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'organization_name' => $data['organization_name'],
                'organization_subtitle' => $data['organization_subtitle'],
                'municipality_id' => $data['municipality_id'],
                'establishment_id' => $data['establishment_id'],
                'usr_phone' => $data['phone'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update user account.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        if ($fromRole !== $user->role) {
            event(new UserRoleChanged($request->user(), $user, $fromRole, $user->role));
        }

        return back()->with('toast', 'User account saved.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // UserPolicy::deactivate() also covers "nobody can change their own status".
        abort_unless($request->user()->can('deactivate', $user), 403);

        $next = $user->status === 'Active' ? 'Inactive' : 'Active';

        try {
            $user->update(['status' => $next]);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle user account status.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        event(new UserAccountStatusChanged($request->user(), $user, $next));

        $verb = $next === 'Active' ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->name}'s account was {$verb}.");
    }

    /**
     * Retries the welcome email from the confirmation panel after an
     * automatic send failed. The passphrase only ever exists in that
     * panel's own DOM (never persisted) — this is the one additional
     * request it travels through, same-origin and CSRF-protected, to
     * reach Mail once more.
     */
    public function resendWelcomeEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'passphrase' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);

        try {
            Mail::to($user->email)->send(new WelcomeAccountCreated($user, $data['passphrase']));

            return response()->json(['sent' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to resend the welcome email.', ['exception' => $e, 'user_id' => $user->id]);

            return response()->json(['sent' => false], 500);
        }
    }

    /**
     * AJAX: establishments in the given municipality with no linked user
     * account yet — feeds the Add User modal's Establishment dropdown.
     */
    public function availableEstablishments(Request $request): JsonResponse
    {
        $data = $request->validate(['municipality_id' => ['required', 'integer', 'exists:municipalities,id']]);

        $establishments = Listing::query()
            ->where('municipality_id', $data['municipality_id'])
            ->where('category', '!=', 'destinations')
            ->whereDoesntHave('establishmentUser')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['establishments' => $establishments]);
    }

    /**
     * Editing keeps the same "nobody is promoted to PTO from here"
     * safeguard as before, reading the role/municipality/establishment
     * fields directly now instead of the old free-text assignment field.
     *
     * @return array{name: string, email: string, role: UserRole, organization_name: string, organization_subtitle: string, municipality_id: ?int, establishment_id: ?int, phone: ?string}
     */
    private function _validatedForUpdate(Request $request, User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'municipality_id' => ['required_unless:role,'.UserRole::PtoAdministrator->value, 'nullable', 'integer', 'exists:municipalities,id'],
            'establishment_id' => ['required_if:role,'.UserRole::Establishment->value, 'nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $role = UserRole::from($data['role']);

        if ($role === UserRole::PtoAdministrator && $user->role !== UserRole::PtoAdministrator) {
            throw ValidationException::withMessages([
                'role' => 'PTO Administrator accounts cannot be granted from this page.',
            ]);
        }

        [$organizationName, $organizationSubtitle, $municipalityId, $establishmentId] = match ($role) {
            UserRole::Establishment => $this->_resolveEstablishmentById((int) $data['establishment_id'], $user),
            UserRole::Lgu => $this->_resolveMunicipalityById((int) $data['municipality_id']),
            UserRole::PtoAdministrator => [$data['name'], $data['name'], null, null],
        };

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'organization_name' => $organizationName,
            'organization_subtitle' => $organizationSubtitle,
            'municipality_id' => $municipalityId,
            'establishment_id' => $establishmentId,
            'phone' => $data['phone'] ?? null,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: ?int, 3: int}
     */
    private function _resolveEstablishmentById(int $establishmentId, ?User $user = null): array
    {
        $listing = Listing::query()->where('id', $establishmentId)->where('category', '!=', 'destinations')->first();

        if (! $listing) {
            throw ValidationException::withMessages(['establishment_id' => 'That establishment could not be found.']);
        }

        $linkedToAnotherUser = User::query()
            ->where('establishment_id', $listing->id)
            ->when($user, fn ($q) => $q->whereKeyNot($user->id))
            ->exists();

        if ($linkedToAnotherUser) {
            throw ValidationException::withMessages(['establishment_id' => "\"{$listing->name}\" already has an account linked to it."]);
        }

        return [$listing->name, "{$listing->barangay}, {$listing->municipality}", $listing->municipality_id, $listing->id];
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: null}
     */
    private function _resolveMunicipalityById(int $municipalityId): array
    {
        $municipality = Municipality::query()->find($municipalityId);

        if (! $municipality) {
            throw ValidationException::withMessages(['municipality_id' => 'That municipality could not be found.']);
        }

        return [$municipality->name, $municipality->name, $municipality->id, null];
    }
}
