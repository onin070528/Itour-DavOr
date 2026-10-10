<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The destination listing workflow — LGU request/resubmit/return, PTO approve & publish/return/unpublish, held changes to Published listings.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\DestinationListingPublished;
use App\Notifications\DestinationListingSubmittedForReview;
use App\Notifications\EstablishmentListingPublished;
use App\Notifications\EstablishmentListingReturned;
use App\Notifications\EstablishmentListingReturnedToLgu;
use App\Notifications\EstablishmentListingSubmittedToLgu;
use App\Support\OperationLogger;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The destination listing of an establishment, on its own listings row
 * (Option C — no second listing row): Not Requested (DRAFT) → Pending PTO
 * Review (FOR_PTO_REVIEW, LGU "Request to feature as tourist destination")
 * → Published (PTO "Approve & Publish") or Returned for Correction
 * (FOR_CORRECTION, PTO remarks required) → resubmitted → Pending PTO Review.
 * LGU edits to public content of a Published listing are held in
 * lst_pending_changes and go through the same Approve / Return decision
 * while the published version stays live. P1: only publish() and
 * unpublish() exist for the PTO-only "live" transition — nothing else in
 * this class, or anywhere else in the app, may set a listing to PUBLISHED
 * or change its live public content on the LGU's behalf.
 * Destination-only records (tourist attractions) use the same workflow;
 * their live status is Active (Listing::liveStatus()). The listing's
 * reporting method, account, and QR are never touched here.
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
     * LGU → PTO: "Request to feature as tourist destination" (from DRAFT,
     * UNPUBLISHED, or FOR_LGU_REVIEW) or "Resubmit to PTO" after a Return
     * for Correction (FOR_CORRECTION). Moves the listing to Pending PTO
     * Review — it never publishes it — clears the PTO's previous remarks,
     * and notifies every PTO Administrator. Re-checked under a row lock so
     * two simultaneous submissions (or a submit racing a return) can't
     * both succeed.
     */
    public function submitToPto(User $objLgu, Listing $objListing): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => in_array($objLocked->lst_status, ['DRAFT', 'UNPUBLISHED', 'FOR_LGU_REVIEW', Listing::STATUS_FOR_CORRECTION], true),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objLgu) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->forceFill(['lst_status' => 'FOR_PTO_REVIEW', 'lst_review_remarks' => null])->save();

                OperationLogger::submitted($objLgu, $objLocked->auditEntityType(), $objLocked->lst_id, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));
                $this->_notifyPto(new DestinationListingSubmittedForReview($objLocked));
            },
        );
    } // end submitToPto

    /**
     * LGU edits to public destination content of a Published listing
     * (Listing::PUBLIC_CONTENT_FIELDS). They are held in
     * lst_pending_changes — the published version stays live — and sent
     * to the PTO for review. $arrProposed holds only the fields that
     * differ from the live listing: an empty array withdraws any held
     * changes (the LGU put the live values back); the same changes as
     * already held and not returned are left alone (no new notification).
     *
     * @param  array<string, mixed>  $arrProposed
     */
    public function submitPendingChanges(User $objLgu, Listing $objListing, array $arrProposed): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->isPubliclyVisible(),
            'This listing is no longer published. Please refresh and try again.',
            function (Listing $objLocked) use ($objLgu, $arrProposed) {
                $arrHeld = $objLocked->lst_pending_changes ?? [];
                $blnIsUnchanged = $arrHeld == $arrProposed && $objLocked->lst_review_remarks === null;

                if ($blnIsUnchanged) {
                    return;
                }

                $arrBefore = $objLocked->getOriginal();
                $objLocked->forceFill([
                    'lst_pending_changes' => $arrProposed === [] ? null : $arrProposed,
                    'lst_review_remarks' => null,
                ])->save();

                // Summary comment: putting the live values back simply withdraws the held changes.
                if ($arrProposed === []) {
                    OperationLogger::updated($objLgu, $objLocked->auditEntityType(), $objLocked->lst_id, $objLocked->mun_id, $objLocked->lst_id, OperationLogger::diff($arrBefore, $objLocked), 'Held changes to the published listing withdrawn.');

                    return;
                }

                OperationLogger::submitted($objLgu, $objLocked->auditEntityType(), $objLocked->lst_id, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));
                $this->_notifyPto(new DestinationListingSubmittedForReview($objLocked, true));
            },
        );
    } // end submitPendingChanges

    /**
     * LGU → establishment. From DRAFT, FOR_LGU_REVIEW, FOR_PTO_REVIEW (the
     * LGU may pull its own submission back before PTO acts on it), or
     * FOR_CORRECTION — returns to DRAFT either way, with a reason the
     * establishment sees.
     */
    public function returnToEstablishment(User $objLgu, Listing $objListing, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => in_array($objLocked->lst_status, ['DRAFT', 'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', Listing::STATUS_FOR_CORRECTION], true),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objLgu, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->forceFill(['lst_status' => 'DRAFT', 'lst_review_remarks' => null])->save();

                OperationLogger::returned($objLgu, $objLocked->auditEntityType(), $objLocked->lst_id, $strReason, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked), $objLocked->lst_id);
                $objLocked->establishmentUser?->notify(new EstablishmentListingReturned($objLocked, $strReason));
            },
        );
    }

    /**
     * PTO "Approve & Publish". A new request (FOR_PTO_REVIEW) goes live
     * (Listing::liveStatus(): PUBLISHED, or Active for a destination-only
     * record); held changes to a Published listing are applied to the
     * live listing. Either way the LGU is notified (and, for a new
     * request, the establishment's account). P1: the controller must have
     * already checked ListingPolicy::publish() — this is the mutation, not
     * the authorization. Nothing else may set a listing to PUBLISHED.
     */
    public function publish(User $objPto, Listing $objListing): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->isAwaitingPtoDecision(),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto) {
                $arrBefore = $objLocked->getOriginal();
                $blnIsChangeRequest = $objLocked->hasPendingChanges();

                if ($blnIsChangeRequest) {
                    $objLocked->forceFill([
                        ...$this->_withLegacyCategory($objLocked->lst_pending_changes),
                        'lst_pending_changes' => null,
                        'lst_review_remarks' => null,
                    ])->save();
                } else {
                    $objLocked->forceFill(['lst_status' => $objLocked->liveStatus(), 'lst_review_remarks' => null])->save();
                }

                OperationLogger::published($objPto, $objLocked->auditEntityType(), $objLocked->lst_id, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked), $blnIsChangeRequest ? 'Changes to the published listing approved.' : null);

                if (! $blnIsChangeRequest) {
                    $objLocked->establishmentUser?->notify(new EstablishmentListingPublished($objLocked));
                }

                $this->_notifyLgu($objLocked, new DestinationListingPublished($objLocked, $blnIsChangeRequest));
            },
        );
    } // end publish

    /**
     * PTO "Return for Correction" (remarks required). A new request
     * (FOR_PTO_REVIEW) moves to FOR_CORRECTION; held changes to a
     * Published listing stay held (the published version stays live).
     * The remarks are stored for the LGU, and every LGU user in the
     * listing's own municipality is notified — "the LGU" is an office, not
     * a single account, and more than one user can hold that role.
     */
    public function returnToLgu(User $objPto, Listing $objListing, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->isAwaitingPtoDecision(),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $blnIsChangeRequest = $objLocked->hasPendingChanges();

                $objLocked->forceFill([
                    ...($blnIsChangeRequest ? [] : ['lst_status' => Listing::STATUS_FOR_CORRECTION]),
                    'lst_review_remarks' => $strReason,
                ])->save();

                OperationLogger::returned($objPto, $objLocked->auditEntityType(), $objLocked->lst_id, $strReason, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked), $objLocked->lst_id);
                $this->_notifyLgu($objLocked, new EstablishmentListingReturnedToLgu($objLocked, $strReason, $blnIsChangeRequest));
            },
        );
    } // end returnToLgu

    /**
     * PTO → UNPUBLISHED. Only from a live listing. Distinct from Suspend/Archive
     * (Pto\DirectoryController::updateStatus(), unchanged) — this is "pull
     * it back for revision," not a compliance or retirement action; the
     * listing re-enters the workflow at UNPUBLISHED, one submit away from
     * review again. Any held changes are folded into the (now hidden)
     * listing, since the whole listing will be reviewed again before it
     * can go live.
     */
    public function unpublish(User $objPto, Listing $objListing, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => $objLocked->isPubliclyVisible(),
            'This listing was already updated by someone else. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $objLocked->forceFill([
                    ...$this->_withLegacyCategory($objLocked->lst_pending_changes ?? []),
                    'lst_status' => 'UNPUBLISHED',
                    'lst_pending_changes' => null,
                    'lst_review_remarks' => null,
                ])->save();

                OperationLogger::unpublished($objPto, $objLocked->auditEntityType(), $objLocked->lst_id, $strReason, $objLocked->mun_id, OperationLogger::diff($arrBefore, $objLocked));
            },
        );
    }

    /**
     * PTO "Change Status" with a required reason (Objective 3, D3): suspend
     * or archive a record, restore an archived destination to Draft, or
     * reinstate a suspended destination to Draft. The allowed targets come
     * from Listing::statusChangeOptions(), re-checked under the row lock. A
     * destination returned to Draft must pass PTO review again before it is
     * public — there is no direct path back to Published. When a destination
     * leaves public view, any held changes are folded into the (now hidden)
     * record, as unpublish() does, because the whole record is reviewed
     * again before it can go live. Establishment Suspend/Archive behavior is
     * unchanged. Policy: ListingPolicy::archive() (PTO only), checked by the
     * controller.
     */
    public function changeStatus(User $objPto, Listing $objListing, string $strNewStatus, string $strReason): void
    {
        $this->_transition(
            $objListing,
            fn (Listing $objLocked) => in_array($strNewStatus, $objLocked->statusChangeOptions(), true),
            'This status change is not allowed from the listing\'s current status. Please refresh and try again.',
            function (Listing $objLocked) use ($objPto, $strNewStatus, $strReason) {
                $arrBefore = $objLocked->getOriginal();
                $arrChanges = ['lst_status' => $strNewStatus];

                // Summary comment: destinations — fold held changes and clear
                // old review remarks; the record is reviewed again anyway.
                if ($objLocked->isDestinationOnly()) {
                    $arrChanges = [
                        ...$this->_withLegacyCategory($objLocked->lst_pending_changes ?? []),
                        ...$arrChanges,
                        'lst_pending_changes' => null,
                        'lst_review_remarks' => null,
                    ];
                }

                $objLocked->forceFill($arrChanges)->save();

                OperationLogger::updated($objPto, $objLocked->auditEntityType(), $objLocked->lst_id, $objLocked->mun_id, $objLocked->lst_id, OperationLogger::diff($arrBefore, $objLocked), $strReason);
            },
        );
    } // end changeStatus

    /**
     * Held public-field values, plus the legacy `category` slug whenever
     * cat_id is among them — cat_id and `category` always change together.
     *
     * @param  array<string, mixed>  $arrChanges
     * @return array<string, mixed>
     */
    private function _withLegacyCategory(array $arrChanges): array
    {
        if (isset($arrChanges['cat_id'])) {
            $arrChanges['lst_category'] = Category::query()->findOrFail($arrChanges['cat_id'])->legacySlug();
        }

        return array_intersect_key($arrChanges, array_flip([...Listing::PUBLIC_CONTENT_FIELDS, 'lst_category']));
    } // end _withLegacyCategory

    /**
     * Every PTO Administrator — the office reviews destination listings.
     */
    private function _notifyPto(Notification $objNotification): void
    {
        User::query()
            ->where('usr_role', UserRole::PtoAdministrator)
            ->where('usr_status', Listing::ACCOUNT_STATUS_ACTIVE)
            ->get()
            ->each(fn (User $objPtoUser) => $objPtoUser->notify($objNotification));
    } // end _notifyPto

    /**
     * Every LGU user of the listing's own municipality.
     */
    private function _notifyLgu(Listing $objListing, Notification $objNotification): void
    {
        User::query()
            ->where('usr_role', UserRole::Lgu)
            ->where('mun_id', $objListing->mun_id)
            ->get()
            ->each(fn (User $objLguUser) => $objLguUser->notify($objNotification));
    } // end _notifyLgu

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
