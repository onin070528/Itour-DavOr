<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO-only user account management — create, edit, and
 * enable/disable PTO, LGU, and Establishment accounts province-wide.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\User;
use App\Support\PtoMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                // The Add User form has no password field — new accounts get
                // the same placeholder password every other demo account in
                // this app uses (see database/seeders/UserSeeder.php), since
                // there's nowhere on screen to set or show a real one yet.
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => $data['role'],
                'organization_name' => $data['organization_name'],
                'organization_subtitle' => $data['organization_subtitle'],
                'status' => 'Active',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create user account.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'User account saved.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        try {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'organization_name' => $data['organization_name'],
                'organization_subtitle' => $data['organization_subtitle'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update user account.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'User account saved.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $next = $user->status === 'Active' ? 'Inactive' : 'Active';

        try {
            $user->update(['status' => $next]);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle user account status.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $verb = $next === 'Active' ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->name}'s account was {$verb}.");
    }

    /**
     * The Add/Edit User modal has a single "Assigned Municipality /
     * Establishment" text field. What it maps to depends on the role:
     *  - Establishment: the exact name of an existing establishment
     *    Listing (this match is load-bearing — EstablishmentMockData's
     *    lookups all key off organization_name) — organization_subtitle
     *    is then derived from that same listing's real barangay/
     *    municipality, the same "{barangay}, {municipality}" shape
     *    UserSeeder already uses for the Botanika demo account.
     *  - Lgu: the municipality itself (organization_subtitle — the field
     *    EnsureLguHasMunicipality / municipality-scoped queries actually
     *    read), validated against the real municipality list.
     *    organization_name has no independent source field to fill it
     *    from, so it's set to the same value rather than invented.
     *  - PtoAdministrator: both fields take the input value as-is.
     *
     * @return array{name: string, email: string, role: UserRole, organization_name: string, organization_subtitle: string}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $roleByTitle = collect(UserRole::cases())->keyBy(fn (UserRole $role) => $role->title());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in($roleByTitle->keys())],
            'assignment' => ['required', 'string', 'max:255'],
        ]);

        $role = $roleByTitle[$data['role']];

        [$organizationName, $organizationSubtitle] = match ($role) {
            UserRole::Establishment => $this->resolveEstablishment($data['assignment']),
            UserRole::Lgu => $this->resolveMunicipality($data['assignment']),
            UserRole::PtoAdministrator => [$data['assignment'], $data['assignment']],
        };

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'organization_name' => $organizationName,
            'organization_subtitle' => $organizationSubtitle,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveEstablishment(string $assignment): array
    {
        $listing = Listing::query()->where('name', $assignment)->where('category', '!=', 'destinations')->first();

        if (! $listing) {
            throw ValidationException::withMessages([
                'assignment' => "No establishment named \"{$assignment}\" was found in the tourism directory.",
            ]);
        }

        return [$listing->name, "{$listing->barangay}, {$listing->municipality}"];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveMunicipality(string $assignment): array
    {
        $exists = collect(TourismCatalog::municipalities())->contains('name', $assignment);

        if (! $exists) {
            throw ValidationException::withMessages([
                'assignment' => "\"{$assignment}\" isn't one of the province's municipalities.",
            ]);
        }

        return [$assignment, $assignment];
    }
}
