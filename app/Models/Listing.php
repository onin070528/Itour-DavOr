<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a tourism destination or establishment listing.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\ImageStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A tourism destination or establishment — the real, DB-backed replacement
 * for App\Support\TourismCatalog::listings(). `category` is 'destinations'
 * for destinations, or one of the non-destination category slugs
 * (TourismCatalog::categories()) for establishments. There is no separate
 * Establishment/Destination model or table — RBAC (see ListingPolicy)
 * branches on `category` instead.
 */
#[Fillable([
    'slug', 'name', 'owner_name', 'category', 'cat_id', 'type', 'license_number', 'accreditation_status',
    'category_note', 'municipality', 'municipality_id', 'barangay', 'lat', 'lng', 'description',
    'rating', 'tags', 'image', 'contact_office', 'contact_phone', 'hours',
    'email', 'website', 'status',
])]
class Listing extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Listing $listing) {
            $listing->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'rating' => 'decimal:1',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * Route-model-bind by slug (e.g. "dahican-beach") — every existing
     * link/route built from a listing's mock 'id' already uses this value.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    /**
     * The approval-workflow photo system (tblestablishment_images) — a
     * separate, newer table from images()/ListingImage above, which stays
     * as the establishment profile's existing unreviewed gallery. Ordered
     * for direct display: cover first, then by sort order.
     */
    public function establishmentImages(): HasMany
    {
        return $this->hasMany(EstablishmentImage::class, 'listing_id')
            ->orderByDesc('img_is_cover')
            ->orderBy('img_sort_order');
    }

    /**
     * I2: the configured live-image cap — the single config value every
     * limit check and every user-facing message below reads from. Never
     * hardcode this number anywhere else.
     */
    public function maxLiveImages(): int
    {
        return (int) config('establishment_images.max_live_images_per_listing');
    }

    /**
     * How many of this establishment's photos currently count toward the
     * live-image cap. PUBLISHED and PENDING rows both count; REJECTED and
     * ARCHIVED never do. A pending Replace request does NOT add to this —
     * it always carries img_replaces_id, so it's excluded here and the
     * Published image it targets is the one actually counted until the
     * replacement is approved (EstablishmentImageReviewer::approve()).
     */
    public function liveImageCount(): int
    {
        return $this->establishmentImages()
            ->whereIn('img_status', [ImageStatus::Published->value, ImageStatus::Pending->value])
            ->whereNull('img_replaces_id')
            ->count();
    }

    /**
     * How many more photos this establishment may add right now — never
     * negative, even for an establishment that already exceeds a
     * since-lowered cap (L2: existing photos are never trimmed).
     */
    public function getRemainingSlots(): int
    {
        return max(0, $this->maxLiveImages() - $this->liveImageCount());
    }

    /**
     * True once no more photos may be added — either exactly at the cap,
     * or already over it (an establishment grandfathered in from a higher
     * limit).
     */
    public function isAtOrOverImageLimit(): bool
    {
        return $this->getRemainingSlots() <= 0;
    }

    /**
     * True only when this establishment already has MORE live photos than
     * the current cap allows — distinct from isAtOrOverImageLimit() so the
     * UI can show the stronger "this already has more than N" notice
     * instead of the plain "you've reached N" one.
     */
    public function isOverImageLimit(): bool
    {
        return $this->liveImageCount() > $this->maxLiveImages();
    }

    /**
     * Validates a prospective upload of $intRequestedCount photos against
     * the live-image cap — returns null when it fits, otherwise the exact
     * user-facing rejection message. The single place this wording lives,
     * so the Form Request's fast pre-check and the Service's locked,
     * authoritative re-check (App\Services\EstablishmentImageUploader)
     * always show identical text.
     */
    public function remainingSlotsErrorMessage(int $intRequestedCount): ?string
    {
        $intRemainingSlots = $this->getRemainingSlots();

        if ($intRemainingSlots <= 0) {
            return "You have reached {$this->maxLiveImages()} photos. Remove one to add another.";
        }

        if ($intRequestedCount > $intRemainingSlots) {
            $strPhotoWord = $intRemainingSlots === 1 ? 'photo' : 'photos';

            return "You can add only {$intRemainingSlots} more {$strPhotoWord}. Please select fewer.";
        }

        return null;
    }

    /**
     * "Photo last updated" (LGU/PTO directory lists) — the date of the
     * latest PUBLISHED image. Callers that already eager-loaded
     * establishmentImages with a PUBLISHED-only constraint avoid an extra
     * query here; everyone else falls back to one.
     */
    public function publishedPhotoLastUpdatedAt(): ?Carbon
    {
        $images = $this->relationLoaded('establishmentImages')
            ? $this->establishmentImages
            : $this->establishmentImages()->where('img_status', 'PUBLISHED')->get();

        return $images->where('img_status', 'PUBLISHED')->max('img_updated_at');
    }

    /**
     * Every PUBLISHED image for the public gallery (7E) — cover first, then
     * by sort order, same ordering establishmentImages() already applies.
     */
    public function publishedGalleryImages(): Collection
    {
        $images = $this->relationLoaded('establishmentImages')
            ? $this->establishmentImages
            : $this->establishmentImages()->where('img_status', 'PUBLISHED')->get();

        return $images->where('img_status', 'PUBLISHED')->values();
    }

    /**
     * The public-facing photo URL (7E: "cards and detail pages show the
     * cover image"): the PUBLISHED cover from the new photo-management
     * system when one exists, otherwise the establishment's original
     * `image` column (today's only photo for every pre-existing listing),
     * otherwise null — callers render the category placeholder for null.
     */
    public function publicCoverImageUrl(): ?string
    {
        $cover = $this->publishedGalleryImages()->firstWhere('img_is_cover', true);

        if ($cover !== null) {
            return route('establishmentImages.file', [$cover, 'full']);
        }

        return $this->image !== null ? asset('storage/itour-images/'.$this->image) : null;
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(Arrival::class);
    }

    public function monthlyArrivalReports(): HasMany
    {
        return $this->hasMany(MonthlyArrivalReport::class);
    }

    public function municipalityRecord(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    /**
     * The fixed-lookup category (tblcategories) — being cut over to from the
     * legacy free-text `category` string column, which is kept untouched
     * until every reader has moved onto this relation.
     */
    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'cat_id', 'cat_id');
    }

    /**
     * Travel & Tours guides are list-only: no map pin, no QR code, no
     * arrival records. `type` is only ever set for Travel & Tours listings.
     */
    public function isTourGuide(): bool
    {
        return $this->type === 'Tour Guide';
    }

    /**
     * Single source of truth for "is this listing visible on public pages
     * right now" — branches on category since destinations and
     * establishments use two different status vocabularies (destinations:
     * Active/Suspended/Archived, unchanged; establishments: the DRAFT →
     * FOR_PTO_REVIEW → PUBLISHED → UNPUBLISHED workflow, plus
     * Suspended/Archived). Every public-facing surface (Explore, landing,
     * the detail page, TourismCatalog, the image file guard, QR
     * eligibility) must call this instead of comparing `status` itself.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->category === 'destinations'
            ? $this->status === 'Active'
            : $this->status === 'PUBLISHED';
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isForPtoReview(): bool
    {
        return $this->status === 'FOR_PTO_REVIEW';
    }

    public function isForLguReview(): bool
    {
        return $this->status === 'FOR_LGU_REVIEW';
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
    }

    public function isUnpublished(): bool
    {
        return $this->status === 'UNPUBLISHED';
    }

    /**
     * Single source of truth for the QR rule: a listing can collect
     * arrivals only if its category allows QR check-in, it is not a Tour
     * Guide, and it is publicly visible. Every QR-related surface
     * (directory, forms, dashboard filters, report breakdowns, public scan)
     * must call this instead of re-checking category/type/status itself.
     */
    public function isQrEnabled(): bool
    {
        return $this->isPubliclyVisible()
            && ! $this->isTourGuide()
            && (bool) $this->categoryRecord?->cat_is_qr_enabled;
    }

    /**
     * The single User account linked to this listing via establishment_id
     * (only ever set when this listing is an establishment, not a
     * destination — see users.establishment_id's unique constraint).
     */
    public function establishmentUser(): HasOne
    {
        return $this->hasOne(User::class, 'establishment_id');
    }

    /**
     * PTO: unrestricted. LGU: only listings (establishments or
     * destinations) in its own municipality. Establishment: only its own
     * linked listing, plus read-only visibility of destinations in its own
     * municipality (per the permission matrix — establishments never see
     * other establishments).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('municipality_id', $user->municipality_id),
            UserRole::Establishment => $query->where(function (Builder $q) use ($user) {
                $q->where('id', $user->establishment_id)
                    ->orWhere(function (Builder $q2) use ($user) {
                        $q2->where('category', 'destinations')->where('municipality_id', $user->municipality_id);
                    });
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
