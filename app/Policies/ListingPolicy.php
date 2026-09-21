<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for the Listing model, covering BOTH resources from
 * the permission matrix — "Establishments" and "Destinations" — since both
 * are rows of the single `listings` table, discriminated by `category`
 * (see Listing's doc comment). Laravel binds one policy per Eloquent
 * model; a same-model second policy class would never be auto-resolved,
 * so the two rule sets are branched on `category` here rather than split
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
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Listing $listing): bool
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $listing->municipality_id === $user->municipality_id,
            UserRole::Establishment => $listing->id === $user->establishment_id
                || ($listing->category === 'destinations' && $listing->municipality_id === $user->municipality_id),
            default => false,
        };
    }

    /**
     * Applies to both establishments and destinations — LGU may create
     * either, within its own municipality (the municipality itself is
     * assigned server-side from the account, not client input — see
     * ManagesDestinationListings::createDestination()).
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::PtoAdministrator, UserRole::Lgu], true);
    }

    /**
     * Establishment users may update only their own linked listing, never
     * a destination and never another establishment. Which *fields* they
     * may change (a "limited" edit per the matrix) is enforced by the
     * controller/validation, not this ability check.
     */
    public function update(User $user, Listing $listing): bool
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $listing->municipality_id === $user->municipality_id,
            UserRole::Establishment => $listing->category !== 'destinations' && $listing->id === $user->establishment_id,
            default => false,
        };
    }

    public function deactivate(User $user, Listing $listing): bool
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $listing->municipality_id === $user->municipality_id,
            default => false,
        };
    }
}
