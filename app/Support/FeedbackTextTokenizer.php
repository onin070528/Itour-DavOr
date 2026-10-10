<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared, deterministic text normalization and tokenization for
 * tourist feedback analysis (Objective 4), plus the fixed negator list.
 * Used by the sentiment scoring and the issue detection so both count
 * and match words the same way. No HTTP calls, no AI.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use Illuminate\Support\Str;

class FeedbackTextTokenizer
{
    /**
     * Words that flip the polarity of the word right after them
     * ("not good" counts as negative, "not crowded" as positive).
     *
     * @var array<int, string>
     */
    public const NEGATORS = ['not', 'never', "isn't", "wasn't", "don't", "doesn't", "didn't", "can't", 'cannot', 'hardly'];

    /**
     * Typographic apostrophes and look-alikes, normalized to a plain
     * apostrophe so "isn’t" and "isn't" are the same token.
     *
     * @var array<int, string>
     */
    private const APOSTROPHES = ["\u{2019}", "\u{2018}", "\u{02BC}", '`'];

    /**
     * Normalize -> lowercase -> tokenize.
     *
     * A token is a run of letters or digits, optionally joined by inner
     * apostrophes ("isn't", "beach's"). Punctuation, symbols, and emoji are
     * separators, and a hyphen splits words ("well-kept" is two tokens).
     * Text that is not valid UTF-8 yields no tokens.
     *
     * @return array<int, string>
     */
    public function tokenize(string $strText): array
    {
        $strNormalized = Str::lower(str_replace(self::APOSTROPHES, "'", $strText));
        $arrMatches = [];
        $mixMatchCount = preg_match_all("/[\p{L}\p{N}]+(?:'[\p{L}\p{N}]+)*/u", $strNormalized, $arrMatches);

        if ($mixMatchCount === false) {
            return [];
        }

        return $arrMatches[0];
    }

    /**
     * Whether a (lowercase) token is one of the fixed negators.
     */
    public function isNegator(string $strToken): bool
    {
        return in_array($strToken, self::NEGATORS, true);
    }
}
