<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO-only user account management — create/edit LGU and
 * Establishment accounts province-wide, plus edit (never create/promote)
 * existing PTO Administrator accounts. See Lgu\UsersController for the
 * LGU-scoped equivalent (LGU creates Establishment accounts within its own
 * municipality only).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PtoMockData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $user = User::query()->create([
                ...$data,
                // The Add User form has no password field — new accounts get
                // a random password nobody knows, and the account holder sets
                // their own via "Forgot password?" on the sign-in page.
                'usr_password' => Str::password(32),
                'usr_email_verified_at' => now(),
                'usr_status' => 'Active',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create user account.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        AuditLogger::record($request->user(), 'user.created', $user, [
            'usr_role' => $user->usr_role?->value,
            'mun_id' => $user->mun_id,
            'lst_id' => $user->lst_id,
        ]);

        return back()->with('toast', 'User account saved.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $before = $user->only(['usr_role', 'mun_id', 'lst_id', 'usr_status']);
        $before['usr_role'] = $before['usr_role']?->value;

        try {
            $user->update($data);
        } catch (\Throwable $e) {
            Log::error('Failed to update user account.', ['exception' => $e, 'usr_id' => $user->usr_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        AuditLogger::recordUserScopeChange($request->user(), $user, $before);

        return back()->with('toast', 'User account saved.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // Nobody — including a PTO administrator — can change their own status.
        abort_if($user->usr_id === $request->user()->usr_id, 403, 'You cannot change the status of your own account.');

        $before = $user->only(['usr_role', 'mun_id', 'lst_id', 'usr_status']);
        $before['usr_role'] = $before['usr_role']?->value;

        $next = $user->usr_status === 'Active' ? 'Inactive' : 'Active';

        try {
            $user->update(['usr_status' => $next]);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle user account status.', ['exception' => $e, 'usr_id' => $user->usr_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        AuditLogger::recordUserScopeChange($request->user(), $user, $before);

        $verb = $next === 'Active' ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->usr_name}'s account was {$verb}.");
    }

    /**
     * The Add/Edit User modal has a single "Assigned Municipality /
     * Establishment" text field. What it maps to depends on the role:
     *  - Establishment: the exact name of an existing establishment
     *    Listing (this match is load-bearing — EstablishmentMockData's
     *    lookups all key off usr_organization_name) — usr_organization_subtitle
     *    is then derived from that same listing's real barangay/
     *    municipality, the same "{barangay}, {municipality}" shape
     *    UserSeeder already uses for the Botanika demo account.
     *    mun_id/lst_id are resolved from that same
     *    Listing row.
     *  - Lgu: the municipality itself (usr_organization_subtitle — the field
     *    EnsureLguHasMunicipality / municipality-scoped queries actually
     *    read), validated against the real `municipalities` table.
     *    usr_organization_name has no independent source field to fill it
     *    from, so it's set to the same value rather than invented.
     *  - PtoAdministrator: both fields take the input value as-is, no
     *    mun_id/lst_id (province-wide scope). Creating
     *    a NEW PTO account, or promoting an existing LGU/Establishment
     *    account TO PtoAdministrator, is rejected here — "nobody can
     *    create an account at their own level or above." Editing an
     *    *existing* PTO account's name/email (role unchanged) is allowed.
     *
     * @return array{usr_name: string, usr_email: string, usr_role: UserRole, usr_organization_name: string, usr_organization_subtitle: string, mun_id: ?int, lst_id: ?int}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $roleByTitle = collect(UserRole::cases())->keyBy(fn (UserRole $role) => $role->title());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tbl_users', 'usr_email')->ignore($user)],
            'role' => ['required', Rule::in($roleByTitle->keys())],
            'assignment' => ['required', 'string', 'max:255'],
        ]);

        $role = $roleByTitle[$data['role']];

        if ($role === UserRole::PtoAdministrator && $user?->usr_role !== UserRole::PtoAdministrator) {
            throw ValidationException::withMessages([
                'role' => 'PTO Administrator accounts cannot be created or granted from this page.',
            ]);
        }

        [$organizationName, $organizationSubtitle, $municipalityId, $establishmentId] = match ($role) {
            UserRole::Establishment => $this->resolveEstablishment($data['assignment'], $user),
            UserRole::Lgu => $this->resolveMunicipality($data['assignment']),
            UserRole::PtoAdministrator => [$data['assignment'], $data['assignment'], null, null],
        };

        return [
            'usr_name' => $data['name'],
            'usr_email' => $data['email'],
            'usr_role' => $role,
            'usr_organization_name' => $organizationName,
            'usr_organization_subtitle' => $organizationSubtitle,
            'mun_id' => $municipalityId,
            'lst_id' => $establishmentId,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: ?int, 3: int}
     */
    private function resolveEstablishment(string $assignment, ?User $user): array
    {
        $listing = Listing::query()->where('lst_name', $assignment)->where('lst_category', '!=', 'destinations')->first();

        if (! $listing) {
            throw ValidationException::withMessages([
                'assignment' => "No establishment named \"{$assignment}\" was found in the tourism directory.",
            ]);
        }

        $linkedToAnotherUser = User::query()
            ->where('lst_id', $listing->lst_id)
            ->when($user, fn ($q) => $q->whereKeyNot($user->usr_id))
            ->exists();

        if ($linkedToAnotherUser) {
            throw ValidationException::withMessages([
                'assignment' => "\"{$assignment}\" already has an account linked to it.",
            ]);
        }

        return [$listing->lst_name, "{$listing->lst_barangay}, {$listing->lst_municipality}", $listing->mun_id, $listing->lst_id];
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: null}
     */
    private function resolveMunicipality(string $assignment): array
    {
        $municipality = Municipality::query()->where('mun_name', $assignment)->first();

        if (! $municipality) {
            throw ValidationException::withMessages([
                'assignment' => "\"{$assignment}\" isn't one of the province's municipalities.",
            ]);
        }

        return [$assignment, $assignment, $municipality->mun_id, null];
    }
}
