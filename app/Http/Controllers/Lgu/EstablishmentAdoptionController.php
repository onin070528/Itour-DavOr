<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : LGU actions that move an establishment between Online iTOUR and Manual/Paper reporting (account activation / suspension).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Models\Listing;
use App\Services\EstablishmentAdoptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reached from the establishment details page (Tourism Directory ->
 * Establishments -> establishment -> iTOUR adoption). Both actions are
 * municipality-scoped from the signed-in LGU account; the account they
 * create or touch is always the establishment's own (users.establishment_id
 * is unique), never one chosen by the request.
 */
class EstablishmentAdoptionController extends LguController
{
    use AuthorizesOwnMunicipality;

    /**
     * Switch to Online iTOUR — creates the establishment's account when it
     * has none (the temporary password is flashed once to the details page
     * and never stored or logged), or reactivates the existing one.
     */
    public function switchToOnline(Request $request, Listing $listing, EstablishmentAdoptionService $objAdoption): RedirectResponse
    {
        $this->_authorizeOwnEstablishment($request, $listing);

        $blnNeedsNewAccount = ! $listing->establishmentUser()->exists();
        $arrNewAccount = null;

        if ($blnNeedsNewAccount) {
            $arrData = $request->validate([
                'account_name' => ['required', 'string', 'max:255'],
                'account_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            ], [
                'account_name.required' => 'Enter the name of the person who will use the account.',
                'account_email.required' => 'Enter the email the establishment will sign in with.',
                'account_email.unique' => 'That email is already used by another iTOUR account.',
            ]);

            $arrNewAccount = ['name' => $arrData['account_name'], 'email' => $arrData['account_email']];
        }

        try {
            $arrResult = $objAdoption->switchToOnline($request->user(), $listing, $arrNewAccount);
        } catch (ValidationException $e) {
            return back()->withInput($request->except('_token'))->withErrors($e->errors())
                ->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        $objRedirect = redirect()->route('lgu.directory.establishments.show', $listing);

        if (! $arrResult['blnIsNewAccount']) {
            return $objRedirect->with('toast', "{$listing->name} now reports through Online iTOUR. Its existing account is active again.");
        }

        // Summary comment: one-time confirmation panel (resources/js/user_account.js).
        return $objRedirect
            ->with('toast', "{$listing->name} now reports through Online iTOUR.")
            ->with('accountCreated', [
                'userId' => $arrResult['user']->id,
                'name' => $arrResult['user']->name,
                'role' => UserRole::Establishment->title(),
                'municipality' => $listing->municipality,
                'passphrase' => $arrResult['passphrase'],
                'emailSent' => $arrResult['emailSent'],
            ]);
    }

    /**
     * Switch to Manual/Paper — suspends the account (never deletes it) and
     * stops QR check-in; history is kept.
     */
    public function switchToManual(Request $request, Listing $listing, EstablishmentAdoptionService $objAdoption): RedirectResponse
    {
        $this->_authorizeOwnEstablishment($request, $listing);

        try {
            $objSuspendedAccount = $objAdoption->switchToManual($request->user(), $listing);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        $strAccountNote = $objSuspendedAccount !== null ? ' Its account is suspended and QR check-in has stopped.' : '';

        return redirect()->route('lgu.directory.establishments.show', $listing)
            ->with('toast', "{$listing->name} now reports on paper.{$strAccountNote}");
    }

    /**
     * Own municipality (403 + security log otherwise), establishments only.
     */
    private function _authorizeOwnEstablishment(Request $request, Listing $objListing): void
    {
        $this->authorizeOwnMunicipality($request, $objListing);
        abort_if($objListing->category === 'destinations', 404);
    }
}
