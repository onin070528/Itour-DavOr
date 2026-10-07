<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for the Listing model, covering BOTH resources from
 * the permission matrix — "Establishments" and "Destinations" — since both
 * are rows of the single `tbl_listings` table, discriminated by `lst_category`
 * (see Listing's doc comment). Laravel binds one policy per Eloquent
 * model; a same-model second policy class would never be auto-resolved,
 * so the two rule sets are branched on `lst_category` here rather than split
 * into two files that would silently only half-work.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $objUser): bool
    {
        return true;
    }

    public function view(User $objUser, Listing $objListing): bool
    {
        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $objListing->mun_id === $objUser->mun_id,
            UserRole::Establishment => $objListing->lst_id === $objUser->lst_id
                || ($objListing->lst_category === 'destinations' && $objListing->mun_id === $objUser->mun_id),
            default => false,
        };
    }

    /**
     * Who may view/download/print an establishment's check-in QR code:
     * the establishment its own listing only, the LGU its own
     * municipality's establishments, the PTO every establishment
     * (read-only). Destinations never have a QR. Whether the listing
     * actually has a usable QR right now is Listing::isAcceptingRegistrations()
     * (checked separately, so out-of-scope users get 403, not 404).
     */
    public function viewQr(User $objUser, Listing $objListing): bool
    {
        if ($objListing->lst_category === 'destinations') {
            return false;
        }

        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $objListing->mun_id === $objUser->mun_id,
            UserRole::Establishment => $objListing->lst_id === $objUser->lst_id,
            default => false,
        };
    }

    /**
     * Who may switch an establishment's QR check-in on or off
     * (lst_is_qr_enabled): the establishment its own listing (e.g. while
     * temporarily closed), the LGU its own municipality's establishments.
     * The PTO stays read-only for QR codes; destinations never have one.
     */
    public function manageQr(User $objUser, Listing $objListing): bool
    {
        if ($objListing->lst_category === 'destinations') {
            return false;
        }

        return match ($objUser->usr_role) {
            UserRole::Lgu => $objListing->mun_id === $objUser->mun_id,
            UserRole::Establishment => $objListing->lst_id === $objUser->lst_id,
            default => false,
        };
    }

    /**
     * Applies to both establishments and destinations — LGU may create
     * either, within its own municipality (the municipality itself is
     * assigned server-side from the account, not client input — see
     * App\Services\AttractionRecordService::create()).
     */
    public function create(User $objUser): bool
    {
        return in_array($objUser->usr_role, [UserRole::PtoAdministrator, UserRole::Lgu], true);
    }

    /**
     * Establishment users may update only their own linked listing, never
     * a destination and never another establishment, and only while their
     * package is editable (DRAFT or UNPUBLISHED) — once submitted, the
     * profile page goes read-only until it's returned. PTO/LGU are
     * unaffected: their own review work is untouched by this status check.
     * Which *fields* an establishment may change (a "limited" edit per the
     * matrix) is enforced by the controller/validation, not this ability.
     */
    public function update(User $objUser, Listing $objListing): bool
    {
        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $objListing->mun_id === $objUser->mun_id,
            UserRole::Establishment => $objListing->lst_category !== 'destinations'
                && $objListing->lst_id === $objUser->lst_id
                && in_array($objListing->lst_status, ['DRAFT', 'UNPUBLISHED'], true),
            default => false,
        };
    }

    public function deactivate(User $objUser, Listing $objListing): bool
    {
        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $objListing->mun_id === $objUser->mun_id,
            default => false,
        };
    }

    /**
     * LGU only, own municipality — requesting a destination listing (or
     * resubmitting it) and returning it to the establishment are gated the
     * same way. Covers establishments and destination-only records alike:
     * both go through the same PTO review (Phase 6). Controllers
     * additionally check the current status before allowing a specific
     * transition; this is the jurisdiction check alone.
     */
    public function submit(User $objUser, Listing $objListing): bool
    {
        return $objUser->usr_role === UserRole::Lgu
            && $objListing->mun_id !== null
            && $objListing->mun_id === $objUser->mun_id;
    }

    /**
     * P1: only the PTO may publish (or unpublish) a listing. The single
     * place this rule is enforced — every publish/unpublish action must
     * check this, never re-derive "PTO only" inline.
     */
    public function publish(User $objUser, Listing $objListing): bool
    {
        // Establishments and destination-only records alike (Phase 6).
        return $objUser->usr_role === UserRole::PtoAdministrator;
    }

    /**
     * Establishment only, own listing, establishments only — the first
     * step of the self-review workflow (DRAFT/UNPUBLISHED →
     * FOR_LGU_REVIEW). Whether the Ready-to-publish checklist actually
     * passes is the controller's job, not this jurisdiction check.
     */
    public function submitToLgu(User $objUser, Listing $objListing): bool
    {
        return $objUser->usr_role === UserRole::Establishment
            && $objListing->lst_category !== 'destinations'
            && $objListing->lst_id === $objUser->lst_id;
    }
}
