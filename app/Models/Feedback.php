<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_feedbacks, one public tourist feedback
 * entry for a destination or tourism establishment listing, with its
 * translation and lexicon-based sentiment result (Objective 4).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\SentimentClassification;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Only the tourist's own form input is fillable. The listing is assigned
 * through Listing::feedbacks()->create(), and every system field — status,
 * consent time, content hash, translation, language, word counts, score,
 * classification, matched terms, failure reason, analyzed time — is set by
 * application logic with forceFill(), never from request input.
 */
#[Table('tbl_feedbacks', key: 'fbk_id')]
#[Fillable(['fbk_tourist_name', 'fbk_original_text', 'fbk_visit_date'])]
class Feedback extends Model
{
    public const CREATED_AT = 'fbk_created_at';

    public const UPDATED_AT = 'fbk_updated_at';

    /**
     * New rows start as pending until the analysis pipeline runs.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'fbk_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'fbk_visit_date' => 'date',
            'fbk_consent_at' => 'datetime',
            'fbk_status' => FeedbackAnalysisStatus::class,
            'fbk_positive_count' => 'integer',
            'fbk_negative_count' => 'integer',
            'fbk_total_word_count' => 'integer',
            'fbk_sentiment_score' => 'decimal:4',
            'fbk_sentiment' => SentimentClassification::class,
            'fbk_matched_terms' => 'array',
            'fbk_analyzed_at' => 'datetime',
        ];
    }

    /**
     * The destination or establishment listing this feedback was submitted
     * for. The link is kept even if the listing is later unpublished,
     * archived, or suspended; its current lst_category decides whether the
     * feedback counts as destination or establishment feedback.
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }

    /**
     * Recurring issue categories detected in this (negative) feedback.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(FeedbackIssue::class, 'fbk_id', 'fbk_id');
    }

    /**
     * Only analyzed feedback counts in official analytics; pending, failed,
     * and rejected rows are always excluded.
     */
    public function scopeAnalyzed(Builder $objQuery): Builder
    {
        return $objQuery->where('fbk_status', FeedbackAnalysisStatus::Analyzed->value);
    }

    /**
     * Feedback the signed-in user may see in feedback analytics:
     *  - PTO: every listing, province-wide.
     *  - LGU: listings in its own municipality, through
     *    Listing::scopeVisibleTo() so the municipality rule is not
     *    duplicated. An LGU account without a mun_id sees nothing.
     *  - Establishment: only its own linked listing (tbl_users.lst_id).
     *    Deliberately NOT Listing::scopeVisibleTo(), which also lets an
     *    establishment read destinations in its municipality for the
     *    directory; that must not extend to tourist feedback. An unlinked
     *    account sees nothing.
     *  - Any other role: nothing.
     * Keep in step with ListingPolicy::viewFeedback().
     */
    public function scopeVisibleTo(Builder $objQuery, User $objUser): Builder
    {
        $blnHasMunicipality = $objUser->mun_id !== null;
        $blnHasLinkedListing = $objUser->lst_id !== null;

        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => $objQuery,
            UserRole::Lgu => $blnHasMunicipality
                ? $objQuery->whereHas('listing', fn (Builder $objListingQuery) => $objListingQuery->visibleTo($objUser))
                : $objQuery->whereRaw('1 = 0'),
            UserRole::Establishment => $blnHasLinkedListing
                ? $objQuery->where('lst_id', $objUser->lst_id)
                : $objQuery->whereRaw('1 = 0'),
            default => $objQuery->whereRaw('1 = 0'),
        };
    }

    /**
     * Feedback for destination-only listings, by the listing's current
     * category.
     */
    public function scopeForDestinations(Builder $objQuery): Builder
    {
        return $objQuery->whereHas('listing', fn (Builder $objListingQuery) => $objListingQuery->where('lst_category', 'destinations'));
    }

    /**
     * Feedback for tourism establishment listings, by the listing's
     * current category.
     */
    public function scopeForEstablishments(Builder $objQuery): Builder
    {
        return $objQuery->whereHas('listing', fn (Builder $objListingQuery) => $objListingQuery->where('lst_category', '!=', 'destinations'));
    }

    public function isAnalyzed(): bool
    {
        return $this->fbk_status === FeedbackAnalysisStatus::Analyzed;
    }
}
