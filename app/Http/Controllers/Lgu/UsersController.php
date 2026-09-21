<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU-only user account management — create/edit/deactivate
 * Establishment accounts within the LGU's own municipality. LGU cannot
 * create LGU or PTO accounts (see Pto\UsersController for that side of
 * the account-creation chain: PTO creates LGU accounts).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsersController extends LguController
{
    /**
     * Users: Establishment accounts in this municipality only.
     */
    public function index(Request $request): View
    {
        $users = User::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get();

        $establishments = Listing::query()
            ->where('municipality_id', $request->user()->municipality_id)
            ->where('category', '!=', 'destinations')
            ->whereDoesntHave('establishmentUser')
            ->orderBy('name')
            ->get();

        return $this->renderLgu($request, 'lgu.users', 'users', 'Users', [
            'users' => $users,
            'establishments' => $establishments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $listing = $data['listing'];

        try {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Same placeholder-password convention as Pto\UsersController —
                // there's no password field on this form yet.
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Establishment,
                'organization_name' => $listing->name,
                'organization_subtitle' => "{$listing->barangay}, {$listing->municipality}",
                'municipality_id' => $request->user()->municipality_id,
                'establishment_id' => $listing->id,
                'status' => 'Active',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create establishment user account.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        AuditLogger::record($request->user(), 'user.created', $user, [
            'role' => $user->role?->value,
            'municipality_id' => $user->municipality_id,
            'establishment_id' => $user->establishment_id,
        ]);

        return back()->with('toast', 'Establishment account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeOwnEstablishmentUser($request, $user);

        // Name/email only — role, municipality_id, establishment_id, and
        // status are never accepted from this endpoint.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ]);

        try {
            $user->update($data);
        } catch (\Throwable $e) {
            Log::error('Failed to update establishment user account.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'User account saved.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeOwnEstablishmentUser($request, $user);
        abort_if($user->id === $request->user()->id, 403, 'You cannot change the status of your own account.');

        $before = $user->only(['role', 'municipality_id', 'establishment_id', 'status']);
        $before['role'] = $before['role']?->value;

        $next = $user->status === 'Active' ? 'Inactive' : 'Active';

        try {
            $user->update(['status' => $next]);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle establishment user account status.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        AuditLogger::recordUserScopeChange($request->user(), $user, $before);

        $verb = $next === 'Active' ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->name}'s account was {$verb}.");
    }

    /**
     * @return array{name: string, email: string, listing: Listing}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'establishment_id' => ['required', 'integer'],
        ]);

        $listing = Listing::query()
            ->where('id', $data['establishment_id'])
            ->where('category', '!=', 'destinations')
            ->first();

        if (! $listing) {
            throw ValidationException::withMessages([
                'establishment_id' => 'Select a valid establishment.',
            ]);
        }

        // Cross-table rule that can't be a DB CHECK constraint (it spans
        // two tables): the establishment being linked must belong to this
        // LGU's own municipality.
        if ($listing->municipality_id !== $request->user()->municipality_id) {
            throw ValidationException::withMessages([
                'establishment_id' => 'That establishment is not in your municipality.',
            ]);
        }

        if (User::query()->where('establishment_id', $listing->id)->exists()) {
            throw ValidationException::withMessages([
                'establishment_id' => 'That establishment already has an account linked to it.',
            ]);
        }

        return ['name' => $data['name'], 'email' => $data['email'], 'listing' => $listing];
    }

    private function authorizeOwnEstablishmentUser(Request $request, User $user): void
    {
        abort_unless(
            $user->role === UserRole::Establishment && $user->municipality_id === $request->user()->municipality_id,
            403
        );
    }
}
