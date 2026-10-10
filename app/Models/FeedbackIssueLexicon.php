<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_feedback_issue_lexicons, the editable
 * keyword/phrase => issue category list, with a cached read that is
 * cleared whenever a row changes.
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

#[Table('tbl_feedback_issue_lexicons', key: 'fil_id')]
#[Fillable(['fil_keyword', 'fil_issue_category', 'fil_weight'])]
class FeedbackIssueLexicon extends Model
{
    public const CREATED_AT = 'fil_created_at';

    public const UPDATED_AT = 'fil_updated_at';

    public const CACHE_KEY = 'tourist_feedback.issue_lexicon';

    /**
     * Any saved or deleted row invalidates the cached keyword list. (Bulk
     * query-builder updates skip model events; call clearCachedLexicon()
     * after one.)
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::clearCachedLexicon());
        static::deleted(fn () => self::clearCachedLexicon());
    }

    protected function casts(): array
    {
        return [
            'fil_weight' => 'integer',
        ];
    }

    /**
     * Keywords and phrases are matched against lowercase text, so they are
     * stored trimmed, lowercase, and with single spaces between words.
     */
    protected function filKeyword(): Attribute
    {
        return Attribute::make(set: fn (string $strKeyword) => Str::lower(Str::squish($strKeyword)));
    }

    /**
     * The whole issue lexicon as keyword => category, read once and cached.
     *
     * @return array<string, string>
     */
    public static function getCachedKeywords(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('fil_issue_category', 'fil_keyword')->all());
    }

    public static function clearCachedLexicon(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
