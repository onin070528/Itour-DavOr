<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a piece of tourist feedback about a destination or establishment.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tourist feedback. Sentiment, polarity and language are computed at
 * submission by App\Services\SentimentAnalyzer and stored here.
 */
#[Table('tbl_feedback', key: 'fbk_id')]
#[Fillable(['lst_id', 'fbk_name', 'fbk_rating', 'fbk_text', 'fbk_language', 'fbk_sentiment', 'fbk_polarity'])]
class Feedback extends Model
{
    use HasFactory;

    public const CREATED_AT = 'fbk_created_at';

    public const UPDATED_AT = 'fbk_updated_at';

    protected function casts(): array
    {
        return [
            'fbk_rating' => 'integer',
            'fbk_polarity' => 'float',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }

    /**
     * Feedback about listings (destinations and establishments) located in
     * one municipality, matched on the listing's municipality label — the
     * same key every LGU data helper uses.
     */
    public function scopeForMunicipality(Builder $objQuery, string $strMunicipality): Builder
    {
        return $objQuery->whereHas('listing', fn (Builder $objListingQuery) => $objListingQuery->where('lst_municipality', $strMunicipality));
    }
}
