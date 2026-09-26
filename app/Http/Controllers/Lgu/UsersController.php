<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU-only establishment registration and account management —
 * register an establishment (listing + its login account) and edit/deactivate
 * Establishment accounts within the LGU's own municipality. LGU cannot
 * create LGU or PTO accounts (see Pto\UsersController for that side of
 * the account-creation chain: PTO creates LGU accounts).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\Listing;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\BusinessHours;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;

class UsersController extends LguController
{
    use ManagesDestinationListings;

    /**
     * Users: Establishment accounts in this municipality only.
     */
    public function index(Request $request): View
    {
        $users = User::query()
            ->visibleTo($request->user())
            ->with('establishment')
            ->orderBy('name')
            ->get();

        return $this->renderLgu($request, 'lgu.users', 'users', 'Users', [
            'users' => $users,
            'categories' => $this->establishmentCategories(),
        ]);
    }

    /**
     * Registers a new establishment (listing) in this LGU's municipality
     * together with the login account linked to it.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedEstablishmentFields($request, Rule::unique('users', 'email'));
        $lgu = $request->user();

        try {
            $user = DB::transaction(function () use ($data, $lgu): User {
                // Municipality always comes from the LGU's own account, never
                // from the request — an LGU can only register establishments
                // inside its own jurisdiction.
                $listing = Listing::query()->create([
                    ...$data['listing'],
                    'slug' => $this->uniqueDestinationSlug($data['listing']['name']),
                    'municipality' => $lgu->organization_subtitle,
                    'municipality_id' => $lgu->municipality_id,
                    'status' => 'Active',
                ]);

                return User::query()->create([
                    'name' => $data['account']['name'],
                    'email' => $data['account']['email'],
                    // Random password nobody knows — the account holder sets
                    // their own via "Forgot password?" on the sign-in page.
                    'password' => Str::password(32),
                    'email_verified_at' => now(),
                    'role' => UserRole::Establishment,
                    'organization_name' => $listing->name,
                    'organization_subtitle' => "{$listing->barangay}, {$listing->municipality}",
                    'municipality_id' => $lgu->municipality_id,
                    'establishment_id' => $listing->id,
                    'status' => 'Active',
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to register establishment and its user account.', ['exception' => $e]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        AuditLogger::record($lgu, 'user.created', $user, [
            'role' => $user->role?->value,
            'municipality_id' => $user->municipality_id,
            'establishment_id' => $user->establishment_id,
        ]);

        return back()->with('toast', "{$user->organization_name} was registered. They can set their password using \"Forgot password?\" on the sign-in page.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeOwnEstablishmentUser($request, $user);

        // Role, municipality_id, establishment_id, and status are never
        // accepted from this endpoint.
        $data = $this->validatedEstablishmentFields($request, Rule::unique('users', 'email')->ignore($user));
        $listing = $user->establishment;

        try {
            DB::transaction(function () use ($data, $user, $listing): void {
                $userFields = $data['account'];

                if ($listing) {
                    $listing->update($data['listing']);
                    $userFields['organization_name'] = $listing->name;
                    $userFields['organization_subtitle'] = "{$listing->barangay}, {$listing->municipality}";
                }

                $user->update($userFields);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to update establishment and its user account.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Establishment information saved.');
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
     * @return array{
     *     listing: array{name: string, category: string, barangay: string, owner_name: string, contact_phone: string, email: string, hours: ?string, website: ?string, description: ?string},
     *     account: array{name: string, email: string}
     * }
     */
    private function validatedEstablishmentFields(Request $request, Unique $uniqueEmail): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_column($this->establishmentCategories(), 'slug'))],
            'barangay' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'contactPhone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', $uniqueEmail],
            'hoursDays' => ['nullable', 'required_with:hoursOpen', Rule::in(array_keys(BusinessHours::days()))],
            'hoursOpen' => ['nullable', 'required_with:hoursDays', Rule::in([...array_keys(BusinessHours::times()), BusinessHours::OPEN_24_HOURS])],
            'hoursClose' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $request->filled('hoursOpen') && $request->input('hoursOpen') !== BusinessHours::OPEN_24_HOURS),
                Rule::in(array_keys(BusinessHours::times())),
                'different:hoursOpen',
            ],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'hoursDays.required_with' => 'Choose which days the establishment is open.',
            'hoursOpen.required_with' => 'Choose an opening time.',
            'hoursClose.required' => 'Choose a closing time.',
            'hoursClose.different' => 'The closing time must be different from the opening time.',
        ]);

        return [
            'listing' => [
                'name' => $data['name'],
                'category' => $data['category'],
                'barangay' => $data['barangay'],
                'owner_name' => $data['ownerName'],
                'contact_phone' => $data['contactPhone'],
                'email' => $data['email'],
                'hours' => BusinessHours::format($data['hoursDays'] ?? null, $data['hoursOpen'] ?? null, $data['hoursClose'] ?? null),
                'website' => $data['website'] ?? null,
                'description' => $data['description'] ?? null,
            ],
            'account' => [
                'name' => $data['ownerName'],
                'email' => $data['email'],
            ],
        ];
    }

    /**
     * Every directory category except destinations — the only kinds of
     * listing that can be registered as an establishment.
     *
     * @return array<int, array{slug: string, label: string, icon: string}>
     */
    private function establishmentCategories(): array
    {
        return array_values(array_filter(
            TourismCatalog::categories(),
            fn (array $category): bool => $category['slug'] !== 'destinations',
        ));
    }

    private function authorizeOwnEstablishmentUser(Request $request, User $user): void
    {
        abort_unless(
            $user->role === UserRole::Establishment && $user->municipality_id === $request->user()->municipality_id,
            403
        );
    }
}
