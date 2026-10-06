<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single creation path for a new account with a temporary passphrase —
 * used by both Pto\UsersController and Lgu\UsersController so every new
 * account gets the same secure first-login behavior.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Events\UserAccountCreated;
use App\Mail\WelcomeAccountCreated;
use App\Models\User;
use App\Support\PassphraseGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class UserAccountProvisioner
{
    /**
     * Creates the account in one transaction with a freshly generated
     * passphrase, fires the audit event, and attempts the welcome email.
     * $arrAttributes must NOT include 'password' or 'usr_must_change_password'
     * — this method owns both. Returns the new User, the plain-text
     * passphrase (for the one-time confirmation panel only — never
     * persisted, never logged), and whether the welcome email went out.
     *
     * For a caller that needs the user created inside a larger
     * transaction of its own (e.g. alongside a new Listing row), use
     * createWithPassphrase() and notifyCreated() instead — see
     * Lgu\UsersController::store().
     *
     * @param  array<string, mixed>  $arrAttributes
     * @return array{user: User, passphrase: string, emailSent: bool}
     */
    public function provision(User $objActor, array $arrAttributes): array
    {
        $arrCreated = DB::transaction(fn () => $this->createWithPassphrase($arrAttributes));

        $blnEmailSent = $this->notifyCreated($objActor, $arrCreated['user'], $arrCreated['passphrase']);

        return [...$arrCreated, 'emailSent' => $blnEmailSent];
    }

    /**
     * Just the row — no transaction of its own, no event, no email. Lets
     * a caller create this user as part of a larger transaction that
     * also writes other rows (e.g. the establishment Listing it's linked
     * to), so a failure anywhere in that transaction leaves nothing
     * orphaned.
     *
     * @param  array<string, mixed>  $arrAttributes
     * @return array{user: User, passphrase: string}
     */
    public function createWithPassphrase(array $arrAttributes): array
    {
        $strPassphrase = PassphraseGenerator::generate();

        $objUser = User::query()->create(array_merge($arrAttributes, [
            'usr_password' => $strPassphrase,
            'usr_must_change_password' => true,
        ]));

        return ['user' => $objUser, 'passphrase' => $strPassphrase];
    }

    /**
     * Fires the audit event and attempts the welcome email — called once
     * the caller's own transaction (if any) has committed.
     */
    public function notifyCreated(User $objActor, User $objUser, string $strPassphrase): bool
    {
        event(new UserAccountCreated($objActor, $objUser));

        return $this->_sendWelcomeEmail($objUser, $strPassphrase);
    }

    /**
     * Never allowed to break account creation — a mail failure only
     * means the creator must hand over the temporary password directly
     * (the confirmation panel is the fallback either way).
     */
    private function _sendWelcomeEmail(User $objUser, string $strPassphrase): bool
    {
        try {
            Mail::to($objUser->usr_email)->send(new WelcomeAccountCreated($objUser, $strPassphrase));

            return true;
        } catch (Throwable $objException) {
            Log::error('Failed to send the welcome email for a new account.', ['exception' => $objException, 'user_id' => $objUser->usr_id]);

            return false;
        }
    }
}
