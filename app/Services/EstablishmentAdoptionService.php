<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Switches an establishment between Online iTOUR and Manual/Paper reporting, activating or suspending its one linked account.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Services;

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Events\UserAccountStatusChanged;
use App\Models\Listing;
use App\Models\User;
use App\Support\OperationLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The only place listings.reporting_mode changes after creation. Keeps the
 * four concerns separate: the establishment record is untouched apart from
 * its reporting method; the account is created, reactivated, or suspended
 * (never deleted); QR eligibility is not stored anywhere — it follows from
 * Listing::isAcceptingRegistrations(). Callers authorize first (LGU, own
 * municipality); this service only re-checks the current state under a
 * row lock so two simultaneous switches cannot both apply.
 */
class EstablishmentAdoptionService
{
    public const ACCOUNT_STATUS_INACTIVE = 'Inactive';

    public function __construct(private readonly UserAccountProvisioner $objProvisioner) {}

    /**
     * Manual/Paper -> Online iTOUR. Reuses the establishment's existing
     * account (reactivating it when suspended) or, when it has none,
     * creates exactly one through UserAccountProvisioner (temporary
     * password shown once, forced change at first login, welcome email).
     * $arrNewAccount (usr_name, usr_email) is only used when no account exists.
     *
     * @param  array{usr_name: string, usr_email: string}|null  $arrNewAccount
     * @return array{user: User, passphrase: ?string, emailSent: ?bool, blnIsNewAccount: bool}
     *
     * @throws ValidationException When the switch no longer applies or a new account's details are missing.
     */
    public function switchToOnline(User $objLgu, Listing $objListing, ?array $arrNewAccount): array
    {
        $arrOutcome = $this->_inTransaction($objListing, function (Listing $objLocked) use ($objLgu, $arrNewAccount): array {
            if ($objLocked->reportingMethod()->isOnline()) {
                throw ValidationException::withMessages(['reporting_mode' => 'This establishment already reports through Online iTOUR.']);
            }

            $arrBefore = $objLocked->getOriginal();
            $objAccount = User::query()->where('lst_id', $objLocked->lst_id)->lockForUpdate()->first();
            $strPassphrase = null;
            $blnIsReactivated = false;

            // Summary comment: one account per establishment — reuse it, or create the first one.
            if ($objAccount === null) {
                if ($arrNewAccount === null) {
                    throw ValidationException::withMessages(['account_email' => 'Enter the account holder\'s name and email to create the account.']);
                }

                $arrCreated = $this->objProvisioner->createWithPassphrase([
                    'usr_name' => $arrNewAccount['usr_name'],
                    'usr_email' => $arrNewAccount['usr_email'],
                    'usr_email_verified_at' => now(),
                    'usr_role' => UserRole::Establishment,
                    'usr_organization_name' => $objLocked->lst_name,
                    'usr_organization_subtitle' => trim("{$objLocked->lst_barangay}, {$objLocked->lst_municipality}", ', '),
                    // Inherited from the establishment — never from the request.
                    'mun_id' => $objLocked->mun_id,
                    'lst_id' => $objLocked->lst_id,
                    'usr_status' => Listing::ACCOUNT_STATUS_ACTIVE,
                    'usr_created_by' => $objLgu->usr_id,
                ]);
                $objAccount = $arrCreated['user'];
                $strPassphrase = $arrCreated['passphrase'];
            } elseif ($objAccount->usr_status !== Listing::ACCOUNT_STATUS_ACTIVE) {
                $objAccount->update(['usr_status' => Listing::ACCOUNT_STATUS_ACTIVE]);
                $blnIsReactivated = true;
            }

            $objLocked->lst_reporting_mode = ReportingMethod::OnlineItour;
            $objLocked->save();

            OperationLogger::updated($objLgu, 'establishment', $objLocked->lst_id, $objLocked->mun_id, $objLocked->lst_id, OperationLogger::diff($arrBefore, $objLocked), 'Switched to Online iTOUR reporting.');

            return ['user' => $objAccount, 'passphrase' => $strPassphrase, 'blnIsReactivated' => $blnIsReactivated];
        });

        // Summary comment: audit events and email only after the transaction committed.
        $blnIsNewAccount = $arrOutcome['passphrase'] !== null;
        $blnEmailSent = null;

        if ($blnIsNewAccount) {
            $blnEmailSent = $this->objProvisioner->notifyCreated($objLgu, $arrOutcome['user'], $arrOutcome['passphrase']);
        }

        if ($arrOutcome['blnIsReactivated']) {
            event(new UserAccountStatusChanged($objLgu, $arrOutcome['user'], Listing::ACCOUNT_STATUS_ACTIVE));
        }

        return [
            'user' => $arrOutcome['user'],
            'passphrase' => $arrOutcome['passphrase'],
            'emailSent' => $blnEmailSent,
            'blnIsNewAccount' => $blnIsNewAccount,
        ];
    }

    /**
     * Online iTOUR -> Manual/Paper. Suspends (never deletes) the linked
     * account, which also ends QR eligibility; the account, its arrivals,
     * and its reports are kept for a later switch back.
     *
     * @return ?User The suspended account, or null when there was none / it was already inactive.
     *
     * @throws ValidationException When the establishment already reports on paper.
     */
    public function switchToManual(User $objLgu, Listing $objListing): ?User
    {
        $objSuspendedAccount = $this->_inTransaction($objListing, function (Listing $objLocked) use ($objLgu): ?User {
            if (! $objLocked->reportingMethod()->isOnline()) {
                throw ValidationException::withMessages(['reporting_mode' => 'This establishment already reports on paper.']);
            }

            $arrBefore = $objLocked->getOriginal();
            $objAccount = User::query()->where('lst_id', $objLocked->lst_id)->lockForUpdate()->first();
            $objSuspended = null;

            if ($objAccount !== null && $objAccount->usr_status === Listing::ACCOUNT_STATUS_ACTIVE) {
                $objAccount->update(['usr_status' => self::ACCOUNT_STATUS_INACTIVE]);
                $objSuspended = $objAccount;
            }

            $objLocked->lst_reporting_mode = ReportingMethod::ManualPaper;
            $objLocked->save();

            OperationLogger::updated($objLgu, 'establishment', $objLocked->lst_id, $objLocked->mun_id, $objLocked->lst_id, OperationLogger::diff($arrBefore, $objLocked), 'Switched to Manual/Paper reporting; account suspended and QR check-in stopped.');

            return $objSuspended;
        });

        if ($objSuspendedAccount !== null) {
            event(new UserAccountStatusChanged($objLgu, $objSuspendedAccount, self::ACCOUNT_STATUS_INACTIVE));
        }

        return $objSuspendedAccount;
    }

    /**
     * Locks the listing row, runs $fnMutate on the locked copy, and turns
     * any unexpected failure into one friendly message (the transaction
     * rolls back, so nothing half-applies).
     *
     * @template TResult
     *
     * @param  \Closure(Listing): TResult  $fnMutate
     * @return TResult
     */
    private function _inTransaction(Listing $objListing, \Closure $fnMutate): mixed
    {
        try {
            return DB::transaction(function () use ($objListing, $fnMutate) {
                $objLocked = Listing::query()->lockForUpdate()->findOrFail($objListing->lst_id);

                return $fnMutate($objLocked);
            });
        } catch (ValidationException $objValidationException) {
            throw $objValidationException;
        } catch (\Throwable $objException) {
            Log::error('Failed to switch an establishment\'s reporting method.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            throw ValidationException::withMessages(['reporting_mode' => 'Something went wrong while saving. Please try again.']);
        }
    }
}
