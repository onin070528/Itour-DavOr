<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU-only Establishment Accounts page — list, edit (account fields
 * only), and enable/disable the Establishment accounts in the LGU's own
 * municipality. Accounts are created from an existing establishment
 * (Lgu\EstablishmentAdoptionController, "Switch to Online iTOUR"), never
 * here. LGU cannot create LGU or PTO accounts (see Pto\UsersController for
 * that side of the account-creation chain: PTO creates LGU accounts).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Events\UserAccountStatusChanged;
use App\Mail\WelcomeAccountCreated;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Support\OperationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsersController extends LguController
{
    /**
     * Users: Establishment accounts in this municipality only.
     */
    public function index(Request $objRequest): View
    {
        $objUsers = User::query()
            ->visibleTo($objRequest->user())
            ->with(['establishment.categoryRecord'])
            ->orderBy('usr_name')
            ->get();

        return $this->renderLgu($objRequest, 'lgu.users', 'users', 'Users', [
            'users' => $objUsers,
            'categories' => Category::query()->active()->forEstablishments()->get(),
        ]);
    }

    /**
     * The old "Add Establishment" form posted here and created an
     * establishment and its account together. That is replaced by the
     * establishment-first flow (register the establishment, then switch it
     * to Online iTOUR to create its account). The route stays so an open
     * old form or bookmark lands somewhere useful; nothing is created.
     */
    public function store(): RedirectResponse
    {
        return redirect()->route('lgu.directory.establishments')
            ->with('toast', 'Accounts are now created from the establishment itself: open the establishment, then choose "Activate Online iTOUR account".');
    }

    /**
     * Edits the account itself — the account holder's name and sign-in
     * email. Establishment details are edited on the establishment page
     * (Lgu\EstablishmentsController). Role, mun_id,
     * lst_id, and status are never accepted from this endpoint.
     */
    public function update(Request $objRequest, User $user): RedirectResponse
    {
        abort_unless($objRequest->user()->can('update', $user), 403);

        $arrData = $objRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tbl_users', 'usr_email')->ignore($user)],
        ]);

        $arrBefore = $user->getOriginal();

        try {
            $user->update([
                'usr_name' => $arrData['name'],
                'usr_email' => $arrData['email'],
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update establishment user account.', ['exception' => $objException, 'user_id' => $user->usr_id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        // OperationLogger::diff() masks the email in the log.
        OperationLogger::updated($objRequest->user(), 'user', $user->usr_id, $user->mun_id, $user->lst_id, OperationLogger::diff($arrBefore, $user));

        return back()->with('toast', 'Account saved.');
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
        abort_unless($objRequest->user()->can('view', $objUser), 403);

        try {
            Mail::to($objUser->usr_email)->send(new WelcomeAccountCreated($objUser, $arrData['passphrase']));

            return response()->json(['sent' => true]);
        } catch (\Throwable $objException) {
            Log::error('Failed to resend the welcome email.', ['exception' => $objException, 'user_id' => $objUser->usr_id]);

            return response()->json(['sent' => false], 500);
        }
    }

    /**
     * Disable / enable an account. Enabling is refused while the linked
     * establishment reports on paper — reactivation goes through "Switch to
     * Online iTOUR" on the establishment, so the account and the reporting
     * method never disagree.
     */
    public function toggleStatus(Request $objRequest, User $user): RedirectResponse
    {
        // UserPolicy::deactivate() also covers "nobody can change their own status".
        abort_unless($objRequest->user()->can('deactivate', $user), 403);

        $strNext = $user->usr_status === Listing::ACCOUNT_STATUS_ACTIVE ? 'Inactive' : Listing::ACCOUNT_STATUS_ACTIVE;
        $objListing = $user->establishment;
        $blnIsEnablingPaperEstablishment = $strNext === Listing::ACCOUNT_STATUS_ACTIVE
            && $objListing !== null
            && ! $objListing->reportingMethod()->isOnline();

        if ($blnIsEnablingPaperEstablishment) {
            return back()
                ->with('toast', "{$objListing->lst_name} reports on paper. Open the establishment and choose \"Switch to Online iTOUR\" to reactivate its account.")
                ->with('toast_tone', 'danger');
        }

        try {
            $user->update(['usr_status' => $strNext]);
        } catch (\Throwable $objException) {
            Log::error('Failed to toggle establishment user account status.', ['exception' => $objException, 'user_id' => $user->usr_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        event(new UserAccountStatusChanged($objRequest->user(), $user, $strNext));

        $strVerb = $strNext === Listing::ACCOUNT_STATUS_ACTIVE ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->usr_name}'s account was {$strVerb}.");
    }
}
