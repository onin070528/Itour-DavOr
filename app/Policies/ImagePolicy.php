<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single source of truth for who may upload, view, approve, and manage establishment images.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\ImageSourceRole;
use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;

/**
 * Every "who can do what" check for tbl_establishment_images lives here —
 * controllers call these methods (via Gate::authorize()/$user->can()),
 * never re-derive the rule inline.
 */
class ImagePolicy
{
    /**
     * I1: who may upload a photo for $objListing. Establishment users may
     * only upload for their own linked listing. LGU may upload on behalf
     * only within their own municipality, and only for a listing with no
     * linked account or whose reporting method is Manual/Paper. PTO may upload for
     * any listing.
     */
    public function uploadFor(User $objUser, Listing $objListing): bool
    {
        return match ($objUser->usr_role) {
            // Establishment self-review (see ListingPolicy::update()): no
            // uploads once the package has been submitted, until it's
            // returned — same editable window as the profile form.
            UserRole::Establishment => $objUser->lst_id === $objListing->lst_id
                && in_array($objListing->lst_status, ['DRAFT', 'UNPUBLISHED'], true),
            UserRole::Lgu => $objUser->mun_id !== null
                && $objUser->mun_id === $objListing->mun_id
                && ($objListing->establishmentUser === null || $objListing->reportingMethod() === ReportingMethod::ManualPaper),
            UserRole::PtoAdministrator => true,
            default => false,
        };
    }

    /**
     * Who may view a non-Published image (Pending/Returned/Archived) — the
     * uploader, whoever is allowed to approve it, or anyone allowed to
     * manage it. Published images are public and never reach this check
     * (see Establishment\ImageFileController).
     */
    public function view(User $objUser, EstablishmentImage $objImage): bool
    {
        if ($objUser->usr_id === $objImage->img_uploaded_by) {
            return true;
        }

        return $this->approve($objUser, $objImage) || $this->manage($objUser, $objImage);
    }

    /**
     * Approval routing (single source of truth, alongside
     * ImageSourceRole::getApproverRole()): an Establishment-sourced image
     * is approved by the LGU Admin of the SAME municipality; an
     * LGU-sourced image is approved only by PTO — an LGU can never approve
     * its own upload or replacement, even one of its own establishments'.
     * PTO uploads auto-publish and are never routed here at all.
     */
    public function approve(User $objUser, EstablishmentImage $objImage): bool
    {
        $objApproverRole = $objImage->img_source_role->getApproverRole();

        if ($objApproverRole === null) {
            return false;
        }

        return match ($objApproverRole) {
            ImageSourceRole::Lgu => $objUser->usr_role === UserRole::Lgu
                && $objUser->mun_id !== null
                && $objUser->mun_id === $objImage->listing->mun_id,
            ImageSourceRole::Pto => $objUser->usr_role === UserRole::PtoAdministrator,
            ImageSourceRole::Establishment => false,
        };
    }

    /**
     * Remove / cover / order / credit / caption — same reach as approve()'s
     * scope, minus the "never your own upload" restriction (I4: these
     * actions need no approval at all).
     */
    public function manage(User $objUser, EstablishmentImage $objImage): bool
    {
        return $this->manageListing($objUser, $objImage->listing);
    }

    /**
     * Same reach as manage() above, but for actions scoped to the
     * establishment itself rather than one specific image — e.g. reorder(),
     * which touches every image on the listing at once.
     */
    public function manageListing(User $objUser, Listing $objListing): bool
    {
        return match ($objUser->usr_role) {
            // Same editable window as uploadFor() above — LGU/PTO keep
            // managing photos regardless of status, their own review work
            // is unaffected.
            UserRole::Establishment => $objUser->lst_id === $objListing->lst_id
                && in_array($objListing->lst_status, ['DRAFT', 'UNPUBLISHED'], true),
            UserRole::Lgu => $objUser->mun_id !== null
                && $objUser->mun_id === $objListing->mun_id,
            UserRole::PtoAdministrator => true,
            default => false,
        };
    }
}
