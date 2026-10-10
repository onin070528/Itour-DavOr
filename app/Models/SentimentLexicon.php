<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_sentiment_lexicons, the editable tourism
 * sentiment word list, with a cached word => polarity read that is cleared
 * whenever a lexicon row changes.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Table('tbl_sentiment_lexicons', key: 'slx_id')]
#[Fillable(['slx_word', 'slx_polarity', 'slx_weight'])]
class SentimentLexicon extends Model
{
    public const CREATED_AT = 'slx_created_at';

    public const UPDATED_AT = 'slx_updated_at';

    public const POLARITY_POSITIVE = 'positive';

    public const POLARITY_NEGATIVE = 'negative';

    public const CACHE_KEY = 'tourist_feedback.sentiment_lexicon';

    /**
     * Any saved or deleted row invalidates the cached lexicon, so an edit
     * takes effect on the next analysis. (Bulk query-builder updates skip
     * model events; call clearCachedLexicon() after one.)
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::clearCachedLexicon());
        static::deleted(fn () => self::clearCachedLexicon());
    }

    protected function casts(): array
    {
        return [
            'slx_weight' => 'integer',
        ];
    }

    /**
     * Words are matched against lowercase tokens, so they are stored
     * trimmed and lowercase.
     */
    protected function slxWord(): Attribute
    {
        return Attribute::make(set: fn (string $strWord) => Str::lower(trim($strWord)));
    }

    /**
     * The whole lexicon as word => polarity, read once and cached.
     *
     * @return array<string, string>
     */
    public static function getCachedPolarities(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('slx_polarity', 'slx_word')->all());
    }

    public static function clearCachedLexicon(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
