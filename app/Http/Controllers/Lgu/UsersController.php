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
    public function index(Request $request): View
    {
        $users = User::query()
            ->visibleTo($request->user())
            ->with(['establishment.categoryRecord'])
            ->orderBy('name')
            ->get();

        return $this->renderLgu($request, 'lgu.users', 'users', 'Users', [
            'users' => $users,
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
     * (Lgu\EstablishmentsController). Role, municipality_id,
     * establishment_id, and status are never accepted from this endpoint.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('update', $user), 403);

        $arrData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ]);

        $arrBefore = $user->getOriginal();

        try {
            $user->update([
                'name' => $arrData['name'],
                'email' => $arrData['email'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update establishment user account.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        // OperationLogger::diff() masks the email in the log.
        OperationLogger::updated($request->user(), 'user', $user->id, $user->municipality_id, $user->establishment_id, OperationLogger::diff($arrBefore, $user));

        return back()->with('toast', 'Account saved.');
    }

    /**
     * Retries the welcome email from the confirmation panel after an
     * automatic send failed — same pattern as Pto\UsersController's
     * equivalent, scoped to the LGU's own municipality.
     */
    public function resendWelcomeEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'passphrase' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);
        abort_unless($request->user()->can('view', $user), 403);

        try {
            Mail::to($user->email)->send(new WelcomeAccountCreated($user, $data['passphrase']));

            return response()->json(['sent' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to resend the welcome email.', ['exception' => $e, 'user_id' => $user->id]);

            return response()->json(['sent' => false], 500);
        }
    }

    /**
     * Disable / enable an account. Enabling is refused while the linked
     * establishment reports on paper — reactivation goes through "Switch to
     * Online iTOUR" on the establishment, so the account and the reporting
     * method never disagree.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // UserPolicy::deactivate() also covers "nobody can change their own status".
        abort_unless($request->user()->can('deactivate', $user), 403);

        $next = $user->status === Listing::ACCOUNT_STATUS_ACTIVE ? 'Inactive' : Listing::ACCOUNT_STATUS_ACTIVE;
        $objListing = $user->establishment;
        $blnIsEnablingPaperEstablishment = $next === Listing::ACCOUNT_STATUS_ACTIVE
            && $objListing !== null
            && ! $objListing->reportingMethod()->isOnline();

        if ($blnIsEnablingPaperEstablishment) {
            return back()
                ->with('toast', "{$objListing->name} reports on paper. Open the establishment and choose \"Switch to Online iTOUR\" to reactivate its account.")
                ->with('toast_tone', 'danger');
        }

        try {
            $user->update(['status' => $next]);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle establishment user account status.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        event(new UserAccountStatusChanged($request->user(), $user, $next));

        $verb = $next === Listing::ACCOUNT_STATUS_ACTIVE ? 'enabled' : 'disabled';

        return back()->with('toast', "{$user->name}'s account was {$verb}.");
    }
}
