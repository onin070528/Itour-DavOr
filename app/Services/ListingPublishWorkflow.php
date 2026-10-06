<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The listing publish workflow — LGU submit/return, PTO publish/return/unpublish — for
 * establishments only.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\EstablishmentListingPublished;
use App\Notifications\EstablishmentListingReturned;
use App\Notifications\EstablishmentListingReturnedToLgu;
use App\Notifications\EstablishmentListingSubmittedToLgu;
use App\Support\OperationLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * DRAFT/UNPUBLISHED → FOR_PTO_REVIEW (LGU submits) → PUBLISHED (PTO
 * publishes) or back to DRAFT (either side returns it). P1: only publish()
 * and unpublish() exist for the PTO-only "live" transition — nothing else
 * in this class, or anywhere else in the app, may set a listing to
 * PUBLISHED. Destinations never pass through here (App\Policies\
 * ListingPolicy::submit()/publish() both refuse them).
 */
class ListingPublishWorkflow
{
    /**
     * Establishment → LGU. Only from DRAFT or UNPUBLISHED — the Ready-to-
     * publish checklist is the controller's job (before calling this),
     * not this service's; by the time this runs, the package is known to
     * be complete.
     */
    public function submitToLgu(User $objEstablishment, Listing $objListing): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => in_array($objLocked->lst_status, ['DRAFT', 'UNPUBLISHED'], true),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objEstablishment) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->update(['lst_status' => 'FOR_LGU_REVIEW']);

                OperationLogger::submitted($objEstablishment, 'establishment', $objLocked->lst_id, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));

                User::query()
                    ->where('usr_role', UserRole::Lgu)
                    ->where('mun_id', $objLocked->mun_id)
                    ->get()
                    ->each(fn (User $objLguUser) => $objLguUser->notify(new EstablishmentListingSubmittedToLgu($objLocked)));
            },
        );
    }

    /**
     * LGU → PTO. Only from DRAFT, UNPUBLISHED, or FOR_LGU_REVIEW (an
     * establishment's reviewed submission) — re-checked under a row lock
     * so two simultaneous submissions (or a submit racing a return) can't
     * both succeed.
     */
    public function submitToPto(User $objLgu, Listing $objListing): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => in_array($objLocked->lst_status, ['DRAFT', 'UNPUBLISHED', 'FOR_LGU_REVIEW'], true),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objLgu) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->update(['lst_status' => 'FOR_PTO_REVIEW']);

                OperationLogger::submitted($objLgu, 'establishment', $objLocked->lst_id, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));
            },
        );
    }

    /**
     * LGU → establishment. From DRAFT, FOR_LGU_REVIEW, or FOR_PTO_REVIEW
     * (the LGU may pull its own submission back before PTO acts on it) —
     * stays/returns to DRAFT either way, with a reason the establishment
     * sees.
     */
    public function returnToEstablishment(User $objLgu, Listing $objListing, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => in_array($objLocked->lst_status, ['DRAFT', 'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW'], true),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objLgu, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->update(['lst_status' => 'DRAFT']);

                OperationLogger::returned($objLgu, 'establishment', $objLocked->lst_id, $strReason, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked), $objLocked->lst_id);
                $objLocked->establishmentUser?->notify(new EstablishmentListingReturned($objLocked, $strReason));
            },
        );
    }

    /**
     * PTO → live. Only from FOR_PTO_REVIEW. P1: the controller must have
     * already checked ImagePolicy::publish() — this is the mutation, not
     * the authorization.
     */
    public function publish(User $objPto, Listing $objListing): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->lst_status === 'FOR_PTO_REVIEW',
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->update(['lst_status' => 'PUBLISHED']);

                OperationLogger::published($objPto, 'establishment', $objLocked->lst_id, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));
                $objLocked->establishmentUser?->notify(new EstablishmentListingPublished($objLocked));
            },
        );
    }

    /**
     * PTO → LGU. Only from FOR_PTO_REVIEW, back to DRAFT. Every LGU user in
     * the listing's own municipality is notified — "the LGU" is an office,
     * not a single account, and more than one user can hold that role.
     */
    public function returnToLgu(User $objPto, Listing $objListing, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->lst_status === 'FOR_PTO_REVIEW',
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->update(['lst_status' => 'DRAFT']);

                OperationLogger::returned($objPto, 'establishment', $objLocked->lst_id, $strReason, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked), $objLocked->lst_id);

                User::query()
                    ->where('usr_role', UserRole::Lgu)
                    ->where('mun_id', $objLocked->mun_id)
                    ->get()
                    ->each(fn (User $objLguUser) => $objLguUser->notify(new EstablishmentListingReturnedToLgu($objLocked, $strReason)));
            },
        );
    }

    /**
     * PTO → UNPUBLISHED. Only from PUBLISHED. Distinct from Suspend/Archive
     * (Pto\DirectoryController::updateStatus(), unchanged) — this is "pull
     * it back for revision," not a compliance or retirement action; the
     * listing re-enters the workflow at UNPUBLISHED, one submit away from
     * review again.
     */
    public function unpublish(User $objPto, Listing $objListing, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->lst_status === 'PUBLISHED',
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->update(['lst_status' => 'UNPUBLISHED']);

                OperationLogger::unpublished($objPto, 'establishment', $objLocked->lst_id, $strReason, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));
            },
        );
    }

    /**
     * Shared transaction shape for every transition above: lock the row,
     * re-check its CURRENT status under that lock (never trust the status
     * the caller read before the request started — someone else may have
     * already decided), run the mutation, and surface a plain message if
     * the precondition no longer holds. A genuine failure inside
     * $fnMutate rolls the whole transaction back — nothing changes.
     *
     * @param  \Closure(Listing): bool  $fnPreconditionHolds
     * @param  \Closure(Listing): void  $fnMutate
     */
    private function _transition(Listing $objListing, \Closure $fnPreconditionHolds, string $strStaleMessage, \Closure $fnMutate): void
    {
        try {
            DB::transaction(function () use ($objListing, $fnPreconditionHolds, $strStaleMessage, $fnMutate) {
                $objLocked = Listing::query()->lockForUpdate()->findOrFail($objListing->lst_id);

                if (! $fnPreconditionHolds($objLocked)) {
                    throw ValidationException::withMessages(['status' => $strStaleMessage]);
                }

                $fnMutate($objLocked);
            });
        } catch (ValidationException $objValidationException) {
            throw $objValidationException;
        } catch (\Throwable $objException) {
            Log::error('Failed to transition a listing in the publish workflow.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            throw ValidationException::withMessages(['status' => 'Something went wrong while saving. Please try again.']);
        }
    }
}
