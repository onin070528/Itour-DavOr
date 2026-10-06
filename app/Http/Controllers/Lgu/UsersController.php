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
use App\Events\UserAccountStatusChanged;
use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Mail\WelcomeAccountCreated;
use App\Models\Listing;
use App\Models\User;
use App\Services\UserAccountProvisioner;
use App\Support\BusinessHours;
use App\Support\OperationLogger;
use App\Support\TourismCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;

class UsersController extends LguController
{
    use ManagesDestinationListings;

    /**
     * Users: Establishment accounts in this municipality only.
     */
    public function index(Request $objRequest): View
    {
        $objUsers = User::query()
            ->visibleTo($objRequest->user())
            ->with('establishment')
            ->orderBy('usr_name')
            ->get();

        return $this->renderLgu($objRequest, 'lgu.users', 'users', 'Users', [
            'users' => $objUsers,
            'categories' => $this->establishmentCategories(),
        ]);
    }

    /**
     * Registers a new establishment (listing) in this LGU's municipality
     * together with the login account linked to it.
     */
    public function store(Request $objRequest, UserAccountProvisioner $objProvisioner): RedirectResponse
    {
        $arrData = $this->validatedEstablishmentFields($objRequest, Rule::unique('tbl_users', 'usr_email'));
        $objLgu = $objRequest->user();

        try {
            $arrCreated = DB::transaction(function () use ($arrData, $objLgu, $objProvisioner): array {
                // Municipality always comes from the LGU's own account, never
                // from the request — an LGU can only register establishments
                // inside its own jurisdiction.
                $objListing = Listing::query()->create([
                    ...$arrData['listing'],
                    'lst_slug' => $this->uniqueDestinationSlug($arrData['listing']['lst_name']),
                    'lst_municipality' => $objLgu->usr_organization_subtitle,
                    'mun_id' => $objLgu->mun_id,
                    // Not public yet — the LGU reviews and submits it to
                    // PTO (App\Services\ListingPublishWorkflow) before it
                    // goes live, same as any other establishment.
                    'lst_status' => 'DRAFT',
                ]);

                return $objProvisioner->createWithPassphrase([
                    'usr_name' => $arrData['account']['usr_name'],
                    'usr_email' => $arrData['account']['usr_email'],
                    'usr_email_verified_at' => now(),
                    'usr_role' => UserRole::Establishment,
                    'usr_organization_name' => $objListing->lst_name,
                    'usr_organization_subtitle' => "{$objListing->lst_barangay}, {$objListing->lst_municipality}",
                    'mun_id' => $objLgu->mun_id,
                    'lst_id' => $objListing->lst_id,
                    'usr_status' => 'Active',
                    'usr_created_by' => $objLgu->usr_id,
                ]);
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to register establishment and its user account.', ['exception' => $objException]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $objUser = $arrCreated['user'];
        $blnEmailSent = $objProvisioner->notifyCreated($objLgu, $objUser, $arrCreated['passphrase']);

        return back()->with('accountCreated', [
            'userId' => $objUser->usr_id,
            'name' => $objUser->usr_name,
            'role' => UserRole::Establishment->title(),
            'municipality' => $objLgu->usr_organization_subtitle,
            'passphrase' => $arrCreated['passphrase'],
            'emailSent' => $blnEmailSent,
        ]);
    }

    public function update(Request $objRequest, User $user): RedirectResponse
    {
        $this->authorizeOwnEstablishmentUser($objRequest, $user);

        // Role, mun_id, lst_id, and status are never
        // accepted from this endpoint.
        $arrData = $this->validatedEstablishmentFields($objRequest, Rule::unique('tbl_users', 'usr_email')->ignore($user));
        $objListing = $user->establishment;
        $arrBefore = $objListing?->getOriginal();

        try {
            DB::transaction(function () use ($arrData, $user, $objListing): void {
                $arrUserFields = $arrData['account'];

                if ($objListing) {
                    $objListing->update($arrData['listing']);
                    $arrUserFields['usr_organization_name'] = $objListing->lst_name;
                    $arrUserFields['usr_organization_subtitle'] = "{$objListing->lst_barangay}, {$objListing->lst_municipality}";
                }

                $user->update($arrUserFields);
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to update establishment and its user account.', ['exception' => $objException, 'user_id' => $user->usr_id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        if ($objListing) {
            OperationLogger::updated($objRequest->user(), 'establishment', $objListing->lst_id, $objListing->mun_id, $objListing->lst_id, OperationLogger::diff($arrBefore, $objListing));
        }

        return back()->with('toast', 'Establishment information saved.');
    }

    /**
     * Retries the welcome email from the confirmation panel after an
     * automatic send failed — same pattern as Pto\UsersController's
     * equivalent, scoped to the LGU's own municipality.
     */
    public function resendWelcomeEmail(Request $objRequest): JsonResponse
    {
        $arrData = $objRequest->validate([
            'user_id' => ['required', 'integer', 'exists:tbl_users,usr_id'],
            'passphrase' => ['required', 'string'],
        ]);

        $objUser = User::query()->findOrFail($arrData['user_id']);
        $this->authorizeOwnEstablishmentUser($objRequest, $objUser);

        try {
            Mail::to($objUser->usr_email)->send(new WelcomeAccountCreated($objUser, $arrData['passphrase']));

            return response()->json(['sent' => true]);
        } catch (\Throwable $objException) {
            Log::error('Failed to resend the welcome email.', ['exception' => $objException, 'user_id' => $objUser->usr_id]);

            return response()->json(['sent' => false], 500);
        }
    }

    public function toggleStatus(Request $objRequest, User $user): RedirectResponse
    {
        $this->authorizeOwnEstablishmentUser($objRequest, $user);
        abort_if($user->usr_id === $objRequest->user()->usr_id, 403, 'You cannot change the status of your own account.');

        $strNext = $user->usr_status === 'Active' ? 'Inactive' : 'Active';

        try {
            $user->update(['usr_status' => $strNext]);
        } catch (\Throwable $objException) {
            Log::error('Failed to toggle establishment user account status.', ['exception' => $objException, 'user_id' => $user->usr_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        event(new UserAccountStatusChanged($objRequest->user(), $user, $strNext));

        $strVerb = $strNext === 'Active' ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->usr_name}'s account was {$strVerb}.");
    }

    /**
     * @return array{
     *     listing: array{lst_name: string, lst_category: string, lst_barangay: string, lst_owner_name: string, lst_contact_phone: string, lst_email: string, lst_hours: ?string, lst_website: ?string, lst_description: ?string},
     *     account: array{usr_name: string, usr_email: string}
     * }
     */
    private function validatedEstablishmentFields(Request $objRequest, Unique $objUniqueEmail): array
    {
        $arrData = $objRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_column($this->establishmentCategories(), 'slug'))],
            'barangay' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'contactPhone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', $objUniqueEmail],
            'hoursDays' => ['nullable', 'required_with:hoursOpen', Rule::in(array_keys(BusinessHours::days()))],
            'hoursOpen' => ['nullable', 'required_with:hoursDays', Rule::in([...array_keys(BusinessHours::times()), BusinessHours::OPEN_24_HOURS])],
            'hoursClose' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $objRequest->filled('hoursOpen') && $objRequest->input('hoursOpen') !== BusinessHours::OPEN_24_HOURS),
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
                'lst_name' => $arrData['name'],
                'lst_category' => $arrData['category'],
                'lst_barangay' => $arrData['barangay'],
                'lst_owner_name' => $arrData['ownerName'],
                'lst_contact_phone' => $arrData['contactPhone'],
                'lst_email' => $arrData['email'],
                'lst_hours' => BusinessHours::format($arrData['hoursDays'] ?? null, $arrData['hoursOpen'] ?? null, $arrData['hoursClose'] ?? null),
                'lst_website' => $arrData['website'] ?? null,
                'lst_description' => $arrData['description'] ?? null,
            ],
            'account' => [
                'usr_name' => $arrData['ownerName'],
                'usr_email' => $arrData['email'],
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
            fn (array $arrCategory): bool => $arrCategory['slug'] !== 'destinations',
        ));
    }

    private function authorizeOwnEstablishmentUser(Request $objRequest, User $objUser): void
    {
        abort_unless(
            $objUser->usr_role === UserRole::Establishment && $objUser->mun_id === $objRequest->user()->mun_id,
            403
        );
    }
}
