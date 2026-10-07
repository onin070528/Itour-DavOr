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
use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Support\SecurityLogger;
use App\Support\TourismCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A tourism destination or establishment — the real, DB-backed replacement
 * for App\Support\TourismCatalog::listings(). `category` is 'destinations'
 * for destinations, or one of the non-destination category slugs
 * (TourismCatalog::categories()) for establishments. There is no separate
 * Establishment/Destination model or table — RBAC (see ListingPolicy)
 * branches on `category` instead.
 *
 * `municipality`/`municipality_id` stay in #[Fillable] for the same reason
 * as on App\Models\User: legitimate create/update calls (seeders, the PTO
 * directory, the LGU's own-municipality create paths) set them explicitly,
 * and no controller ever passes raw request input for them. Once assigned,
 * only a signed-in PTO Administrator may move a listing to another
 * municipality — enforced in booted() below, whichever code path tries.
 * `reporting_mode` is deliberately NOT fillable: switching it has side
 * effects (QR, account), so it is only ever set explicitly.
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

    /** getQrStatus(): QR works — scans open the check-in form. */
    public const QR_STATUS_ACTIVE = 'active';

    /** getQrStatus(): eligible, but the establishment/LGU switched QR check-in off. */
    public const QR_STATUS_SWITCHED_OFF = 'switched_off';

    /** getQrStatus(): reports online, but has no active linked account (none yet, or suspended). */
    public const QR_STATUS_NO_ACCOUNT = 'no_account';

    /** getQrStatus(): eligible establishment that reports on paper — arrivals go through LGU manual entry. */
    public const QR_STATUS_MANUAL_REPORTING = 'manual_reporting';

    /** getQrStatus(): a destination, Tour Guide, suspended/archived establishment, or QR-disabled category. */
    public const QR_STATUS_NOT_ELIGIBLE = 'not_eligible';

    /** users.status of an account that can sign in and collect QR arrivals. */
    public const ACCOUNT_STATUS_ACTIVE = 'Active';

    /** The live statuses (liveStatus()): PUBLISHED establishments, Active destination-only records. */
    public const LIVE_STATUSES = ['PUBLISHED', 'Active'];

    /** Destination listing returned by the PTO to the LGU with remarks (lst_review_remarks). */
    public const STATUS_FOR_CORRECTION = 'FOR_CORRECTION';

    /** Establishment statuses that take it out of operation (Pto\DirectoryController::updateStatus()). */
    public const INACTIVE_ESTABLISHMENT_STATUSES = ['Suspended', 'Archived'];

    /**
     * The tour guide type used before the R13 type list existed — still
     * recognized by isTourGuide() so any row or fixture carrying it keeps
     * its list-only behavior.
     */
    public const LEGACY_TOUR_GUIDE_TYPE = 'Tour Guide';

    /**
     * The fields that make up public destination content. Nothing in this
     * list reaches the public site without PTO approval (R10): while a new
     * request is with the PTO they are locked for LGU edits
     * (hasLockedPublicContent()), and LGU edits to a Published listing are
     * held in lst_pending_changes until the PTO approves them. Contact and
     * operating fields are not public destination content and always save.
     *
     * @var array<int, string>
     */
    public const PUBLIC_CONTENT_FIELDS = ['name', 'cat_id', 'type', 'category_note', 'barangay', 'lat', 'lng', 'description'];

    protected static function booted(): void
    {
        static::creating(function (Listing $listing) {
            $listing->uuid ??= (string) Str::uuid();
        });

        // Summary comment: a listing's municipality is fixed once assigned.
        // Only a signed-in PTO Administrator may move it; any other signed-in
        // actor gets a 403 and a security log entry. Contexts with no
        // signed-in user (seeders, console commands, queued jobs) are left
        // alone so structural backfills such as RbacScopeBackfillSeeder keep
        // working — same rule as App\Models\User's LGU reassignment guard.
        static::updating(function (Listing $objListing) {
            $blnIsReassigning = $objListing->isDirty('municipality_id') && $objListing->getOriginal('municipality_id') !== null;

            if (! $blnIsReassigning) {
                return;
            }

            $objActor = Auth::user();
            $blnIsBlockedActor = $objActor !== null && ! $objActor->isPto();

            if ($blnIsBlockedActor) {
                SecurityLogger::accessDenied($objActor, 'listing_municipality_reassign', Listing::class, $objListing->municipality_id);

                throw new AuthorizationException('Only the Provincial Tourism Office can move a listing to another municipality.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'rating' => 'decimal:1',
            'lat' => 'float',
            'lng' => 'float',
            'lst_is_qr_enabled' => 'boolean',
            'lst_pending_changes' => 'array',
            'reporting_mode' => ReportingMethod::class,
        ];
    }

    /**
     * Assigns a uuid to every listing that has none and returns how many
     * were filled. Needed because seeding through DatabaseSeeder runs
     * WithoutModelEvents, which skips booted()'s creating hook. Existing
     * uuids are never touched, so QR codes already printed keep working.
     */
    public static function backfillMissingUuids(): int
    {
        $intFilledCount = 0;

        // Summary comment: one quiet save per row — no events, no timestamps
        // bump beyond the uuid itself.
        static::query()->whereNull('uuid')->orderBy('id')->each(function (Listing $objListing) use (&$intFilledCount) {
            $objListing->forceFill(['uuid' => (string) Str::uuid()])->saveQuietly();
            $intFilledCount++;
        }); // end each listing without uuid

        return $intFilledCount;
    }

    /**
     * A slug from $strName not used by any listing yet ("aliwagwag-falls",
     * then "aliwagwag-falls-2", ...). Slugs are the public URL of a listing.
     */
    public static function uniqueSlug(string $strName): string
    {
        $strBase = Str::slug($strName) ?: 'destination';
        $strSlug = $strBase;
        $intSuffix = 2;

        while (static::query()->where('slug', $strSlug)->exists()) {
            $strSlug = "{$strBase}-{$intSuffix}";
            $intSuffix++;
        } // end while slug taken

        return $strSlug;
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
     * arrival records. The guide type comes from the single type list
     * (config/establishment_categories.php 'tour_guide_type'); the legacy
     * pre-R13 value is still recognized.
     */
    public function isTourGuide(): bool
    {
        return self::isTourGuideType($this->type);
    }

    /**
     * Whether a (submitted or stored) type value is the tour guide type —
     * for form validation that runs before any Listing exists.
     */
    public static function isTourGuideType(?string $strType): bool
    {
        $arrTourGuideTypes = [config('establishment_categories.tour_guide_type'), self::LEGACY_TOUR_GUIDE_TYPE];

        return in_array($strType, $arrTourGuideTypes, true);
    }

    /**
     * How this establishment reports tourist arrivals (Online iTOUR or
     * Manual/Paper). Falls back to the default for a row read without the
     * column, so callers always get an enum.
     */
    public function reportingMethod(): ReportingMethod
    {
        return $this->reporting_mode instanceof ReportingMethod
            ? $this->reporting_mode
            : ReportingMethod::default();
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
     * The PTO returned the destination listing request to the LGU with
     * remarks (lst_review_remarks); the LGU corrects and resubmits it.
     */
    public function isForCorrection(): bool
    {
        return $this->status === self::STATUS_FOR_CORRECTION;
    }

    /**
     * Whether this is a destination-only record (a tourist attraction such
     * as a waterfall or beach): no account, no QR, no reporting method.
     */
    public function isDestinationOnly(): bool
    {
        return $this->category === 'destinations';
    }

    /**
     * The status the PTO's "Approve & Publish" sets — the live status of
     * this record type (see isPubliclyVisible()): PUBLISHED for an
     * establishment, Active for a destination-only record, so existing
     * destinations and every public page keep their vocabulary.
     */
    public function liveStatus(): string
    {
        return $this->isDestinationOnly() ? 'Active' : 'PUBLISHED';
    }

    /**
     * The operation_logs entity type for this record.
     */
    public function auditEntityType(): string
    {
        return $this->isDestinationOnly() ? 'destination' : 'establishment';
    }

    /**
     * The LGU details page of this record, as a relative path (used in
     * notifications, so the bell only ever redirects within iTOUR).
     */
    public function lguDetailsPath(): string
    {
        return $this->isDestinationOnly()
            ? route('lgu.directory.attractions.show', $this, false)
            : route('lgu.directory.establishments.show', $this, false);
    }

    /**
     * A live (Published) listing whose LGU edits to public content are
     * held for PTO review (the published version stays live meanwhile).
     */
    public function hasPendingChanges(): bool
    {
        return $this->isPubliclyVisible() && ! empty($this->lst_pending_changes);
    }

    /**
     * The submitted public destination fields (PUBLIC_CONTENT_FIELDS)
     * whose values differ from the live listing — number and blank/null
     * differences normalized, so an untouched form proposes nothing.
     *
     * @param  array<string, mixed>  $arrSubmitted
     * @return array<string, mixed>
     */
    public function publicFieldChanges(array $arrSubmitted): array
    {
        $arrChanged = [];

        foreach (array_intersect_key($arrSubmitted, array_flip(self::PUBLIC_CONTENT_FIELDS)) as $strField => $mixValue) {
            $mixLive = $this->getAttribute($strField);
            $blnIsNumericPair = is_numeric($mixValue) && is_numeric($mixLive);
            $blnIsSame = $blnIsNumericPair
                ? (float) $mixValue === (float) $mixLive
                : (string) ($mixValue ?? '') === (string) ($mixLive ?? '');

            if (! $blnIsSame) {
                $arrChanged[$strField] = $mixValue;
            }
        } // end foreach submitted public field

        return $arrChanged;
    }

    /**
     * Held changes the PTO returned for correction — they wait for the
     * LGU to correct and resubmit them.
     */
    public function hasReturnedPendingChanges(): bool
    {
        return $this->hasPendingChanges() && $this->lst_review_remarks !== null;
    }

    /**
     * Whether the PTO has something to decide on this listing: a new
     * request (Pending PTO Review), or held changes to a Published one
     * that have not been returned.
     */
    public function isAwaitingPtoDecision(): bool
    {
        return $this->isForPtoReview() || ($this->hasPendingChanges() && ! $this->hasReturnedPendingChanges());
    }

    /**
     * Listings the PTO has to decide on (see isAwaitingPtoDecision()).
     */
    public function scopeAwaitingPtoDecision(Builder $query): Builder
    {
        return $query->where(fn (Builder $objQuery) => $objQuery
            ->where('status', 'FOR_PTO_REVIEW')
            ->orWhere(fn (Builder $objLive) => $objLive
                ->whereIn('status', self::LIVE_STATUSES)
                ->whereNotNull('lst_pending_changes')
                ->whereNull('lst_review_remarks')));
    }

    /**
     * Listings back with their LGU for correction: a returned request, or
     * returned held changes to a live listing.
     */
    public function scopeReturnedForCorrection(Builder $query): Builder
    {
        return $query->where(fn (Builder $objQuery) => $objQuery
            ->where('status', self::STATUS_FOR_CORRECTION)
            ->orWhere(fn (Builder $objLive) => $objLive
                ->whereIn('status', self::LIVE_STATUSES)
                ->whereNotNull('lst_pending_changes')
                ->whereNotNull('lst_review_remarks')));
    }

    /**
     * Whether public destination content (PUBLIC_CONTENT_FIELDS) is locked
     * for LGU edits: only while the PTO is reviewing a new request. Edits
     * to a Published listing are allowed but held for PTO review instead
     * (ListingPublishWorkflow::submitPendingChanges()).
     */
    public function hasLockedPublicContent(): bool
    {
        return $this->isForPtoReview();
    }

    /**
     * A copy of this listing with any held changes filled in, for showing
     * the LGU its proposed content in the edit form. Never saved.
     */
    public function withPendingChanges(): self
    {
        $objCopy = clone $this;

        if ($this->hasPendingChanges()) {
            $objCopy->forceFill($this->lst_pending_changes);
        }

        return $objCopy;
    }

    /**
     * The destination listing state in plain words, for establishment
     * lists and details (R12). Derived from the review state only — it
     * does not affect reporting, the account, or QR.
     */
    public function destinationListingLabel(): string
    {
        if ($this->hasReturnedPendingChanges()) {
            return 'Published · Changes returned for correction';
        }

        if ($this->hasPendingChanges()) {
            return 'Published · Changes pending PTO review';
        }

        return match ($this->status) {
            'DRAFT' => 'Not Requested',
            'FOR_LGU_REVIEW' => 'Waiting for your review',
            'FOR_PTO_REVIEW' => 'Pending PTO Review',
            self::STATUS_FOR_CORRECTION => 'Returned for Correction',
            // A destination-only record is live as Active (liveStatus()).
            'PUBLISHED', 'Active' => 'Published',
            'UNPUBLISHED' => 'Unpublished',
            default => (string) $this->status,
        };
    }

    /**
     * The linked establishment account in plain words: "Active",
     * "Suspended" (kept, but cannot sign in), or "None".
     */
    public function accountStatusLabel(): string
    {
        if ($this->establishmentUser === null) {
            return 'None';
        }

        return $this->hasActiveAccount() ? 'Active' : 'Suspended';
    }

    /**
     * The category name to display: the fixed-lookup category when set,
     * otherwise the legacy slug's label.
     */
    public function categoryName(): string
    {
        return $this->categoryRecord?->cat_name ?? TourismCatalog::categoryLabel((string) $this->category);
    }

    /**
     * Single source of truth for "is this establishment eligible for
     * tourist arrival recording": its category allows QR check-in, it is
     * not a Tour Guide, it is not Suspended or Archived, and it has a
     * `uuid` to encode into the QR/check-in link. Deliberately NOT tied to
     * the destination listing (Phase 3, D4): an establishment that was
     * never featured publicly still records arrivals and is still covered
     * by reports. Every QR-related surface (directory, forms, dashboard
     * filters, report breakdowns, public scan) must call this instead of
     * re-checking category/type/status itself. The `uuid` check guards
     * against a pre-uuid-era or directly-inserted row with no uuid, which
     * would otherwise crash route('lgu.establishmentQr', ...) in the
     * Tourism Directory's QR modal.
     */
    public function isQrEnabled(): bool
    {
        return $this->uuid !== null
            && $this->category !== 'destinations'
            && ! in_array($this->status, self::INACTIVE_ESTABLISHMENT_STATUSES, true)
            && ! $this->isTourGuide()
            && (bool) $this->categoryRecord?->cat_is_qr_enabled;
    }

    /**
     * Whether the linked establishment account exists and can sign in.
     */
    public function hasActiveAccount(): bool
    {
        return $this->establishmentUser?->status === self::ACCOUNT_STATUS_ACTIVE;
    }

    /**
     * Single source of truth for "can this listing's QR code be used right
     * now": the public check-in form, and who may view/download/print the
     * QR, all call this. Only establishments take part — destinations are
     * public directory records and never get a check-in QR. Builds on
     * isQrEnabled() (category, type, status, uuid) and adds: the Online
     * iTOUR reporting method, an ACTIVE linked account, and the
     * per-establishment switch (lst_is_qr_enabled). A Manual/Paper
     * establishment, or one whose account is suspended, gets no QR — its
     * arrivals go through LGU manual/paper entry instead. Publication as a
     * destination is never required. Report and dashboard filters keep
     * using isQrEnabled(), so this never changes which listings a report
     * covers.
     */
    public function isAcceptingRegistrations(): bool
    {
        if ($this->category === 'destinations') {
            return false;
        }

        $blnIsQrEligible = $this->isQrEnabled();
        $blnIsOnlineReporting = $this->reportingMethod()->isOnline();
        $blnHasActiveAccount = $this->hasActiveAccount();
        $blnIsSwitchedOn = $this->lst_is_qr_enabled !== false;

        return $blnIsQrEligible && $blnIsOnlineReporting && $blnHasActiveAccount && $blnIsSwitchedOn;
    }

    /**
     * Why this listing's QR is (or isn't) usable, for screens that show a
     * QR status — one of the QR_STATUS_* constants. Derived from
     * isAcceptingRegistrations(), never re-deciding eligibility itself.
     */
    public function getQrStatus(): string
    {
        if ($this->isAcceptingRegistrations()) {
            return self::QR_STATUS_ACTIVE;
        }

        if (! $this->isQrEnabled()) {
            return self::QR_STATUS_NOT_ELIGIBLE;
        }

        if (! $this->reportingMethod()->isOnline()) {
            return self::QR_STATUS_MANUAL_REPORTING;
        }

        if (! $this->hasActiveAccount()) {
            return self::QR_STATUS_NO_ACCOUNT;
        }

        return self::QR_STATUS_SWITCHED_OFF;
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
